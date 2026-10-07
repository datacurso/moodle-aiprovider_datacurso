<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\local;

use aiprovider_datacurso\httpclient\datacurso_api;

/**
 * The region a licence belongs to, resolved once and kept.
 *
 * Which region serves a site decides the host every request goes to, and it is a property of the
 * licence: it changes when the licence changes and at no other time. It used to be asked of the
 * shop while building every client, so each question a user asked cost a round trip to the shop
 * first, and a shop that did not answer took the whole service down with it although the service
 * itself was up.
 *
 * On Workplace each tenant has its own licence (or falls back to the site licence), so the region
 * is kept per tenant, with the fingerprint of the licence that tenant uses. A site without tenancy
 * keeps it in the site configuration.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class license_region {
    /** @var string Plugin the configuration belongs to. */
    private const PLUGIN = 'aiprovider_datacurso';

    /** @var string Config holding the region: '1' for the European deployment. */
    public const REGION = 'licenseregion';

    /** @var string Config holding the fingerprint of the licence the region was resolved for. */
    public const FINGERPRINT = 'licenseregion_key';

    /** @var string Config holding when the region was last resolved. */
    public const CHECKED = 'licenseregion_checked';

    /**
     * @var int How long a resolved region is trusted before it is asked for again.
     *
     * Not about the licence changing, which the setting sees at once and forgets the answer for:
     * about this side finding out when the region of a licence is moved without the key changing.
     */
    public const TTL = WEEKSECS;

    /**
     * Whether the licence of a tenant belongs to the European deployment.
     *
     * @param int|null $tenantid Tenant to answer for; defaults to the tenant of the current user.
     * @param string|null $licensekey Licence to answer for; defaults to the licence of the tenant.
     * @return bool
     * @throws \moodle_exception When the region is not known and the shop cannot be asked.
     */
    public static function is_european(?int $tenantid = null, ?string $licensekey = null): bool {
        $tenantid = $tenantid ?? tenant_resolver::get_tenant_id();
        $licence = self::licence($tenantid, $licensekey);
        $fingerprint = self::fingerprint($licence);
        $stored = self::read(self::REGION, $tenantid);
        $known = self::is_known($stored, $fingerprint, $tenantid);

        if ($known && self::is_fresh($tenantid)) {
            return (bool)$stored;
        }

        try {
            $european = static::ask_the_shop($licence);
        } catch (\moodle_exception $e) {
            return self::fall_back($e, $known, $stored);
        }

        self::remember($european, $fingerprint, $tenantid);

        return $european;
    }

    /**
     * Forget every resolved region, so that the next request of every tenant asks for it again.
     *
     * Called when the site licence key is saved: the licence is the only thing that decides the
     * region, and every tenant without a licence of its own uses the site one, so the answers are
     * dropped the moment it changes instead of waiting for them to go stale.
     */
    public static function forget(): void {
        unset_config(self::REGION, self::PLUGIN);
        unset_config(self::FINGERPRINT, self::PLUGIN);
        unset_config(self::CHECKED, self::PLUGIN);
        tenant_config::delete_names(self::PLUGIN, self::names());
    }

    /**
     * Forget the region resolved for one tenant, so that its next request asks for it again.
     *
     * Called when the licence of that tenant is saved on the tenant configuration page.
     *
     * @param int $tenantid
     */
    public static function forget_for_tenant(int $tenantid): void {
        if ($tenantid === tenant_resolver::NO_TENANT) {
            unset_config(self::REGION, self::PLUGIN);
            unset_config(self::FINGERPRINT, self::PLUGIN);
            unset_config(self::CHECKED, self::PLUGIN);
            return;
        }

        tenant_config::delete_names(self::PLUGIN, self::names(), $tenantid);
    }

    /**
     * Ask the shop which region a licence belongs to.
     *
     * Kept as a separate step so tests can answer for the shop without touching the network.
     *
     * @param string $licensekey Licence to ask about.
     * @return bool
     * @throws \moodle_exception When the shop cannot be reached or the licence is not configured.
     */
    protected static function ask_the_shop(string $licensekey): bool {
        $api = new datacurso_api($licensekey);
        $response = $api->get('tokens/saldo');

        return !empty($response['is_for_eu']);
    }

    /**
     * What to answer when the shop could not be asked.
     *
     * @param \moodle_exception $failure What the shop failed with.
     * @param bool $known Whether the region of this licence was resolved before.
     * @param string|null $stored The region resolved before, as it is stored.
     * @return bool
     * @throws \moodle_exception When nothing is known, because guessing would send the data of a
     *                           site to a region it may not be allowed to reach.
     */
    private static function fall_back(\moodle_exception $failure, bool $known, ?string $stored): bool {
        if (!$known) {
            throw $failure;
        }

        // The region of this licence is already known, so a shop that does not answer is no
        // reason to stop: it is asked again on the next request.
        $class = get_class($failure);
        debugging('Datacurso shop unreachable, keeping the known region: ' . $class, DEBUG_DEVELOPER);

        return (bool)$stored;
    }

    /**
     * Whether the region stored for a tenant belongs to the licence in force.
     *
     * @param string|null $stored The region stored, null when there is none.
     * @param string $fingerprint Fingerprint of the licence in force.
     * @param int $tenantid
     * @return bool
     */
    private static function is_known(?string $stored, string $fingerprint, int $tenantid): bool {
        if ($stored === null) {
            return false;
        }
        $knownfor = self::read(self::FINGERPRINT, $tenantid);

        return $knownfor === $fingerprint;
    }

    /**
     * Whether the region stored for a tenant is recent enough to be trusted.
     *
     * @param int $tenantid
     * @return bool
     */
    private static function is_fresh(int $tenantid): bool {
        $checked = self::read(self::CHECKED, $tenantid);
        $age = time() - (int)$checked;

        return $age < self::TTL;
    }

    /**
     * Keep the region, and the licence it belongs to, for the requests that follow.
     *
     * @param bool $european Whether the licence belongs to the European deployment.
     * @param string $fingerprint Fingerprint of the licence it was resolved for.
     * @param int $tenantid
     */
    private static function remember(bool $european, string $fingerprint, int $tenantid): void {
        if ($european) {
            $region = '1';
        } else {
            $region = '0';
        }
        self::write(self::REGION, $region, $tenantid);
        self::write(self::FINGERPRINT, $fingerprint, $tenantid);
        self::write(self::CHECKED, (string)time(), $tenantid);
    }

    /**
     * The licence a tenant uses: the one given, or its own, or the site licence it falls back to.
     *
     * Resolved the same way as the API clients do, so the region kept is the one of the licence
     * the requests are actually sent with.
     *
     * @param int $tenantid
     * @param string|null $licensekey Licence given explicitly, if any.
     * @return string The licence, or an empty string when there is none.
     */
    private static function licence(int $tenantid, ?string $licensekey): string {
        $given = trim((string)$licensekey);
        if ($given !== '') {
            return $given;
        }

        $configured = tenant_config::get(self::PLUGIN, $tenantid, 'licensekey', '');

        return trim((string)$configured);
    }

    /**
     * A fingerprint of a licence, so a new licence resolves its own region.
     *
     * The key itself is already in the configuration; what is kept here is only enough to tell one
     * licence from another.
     *
     * @param string $licence
     * @return string
     */
    private static function fingerprint(string $licence): string {
        if ($licence === '') {
            return '';
        }

        return sha1($licence);
    }

    /**
     * Read a stored value: from the site configuration without tenancy, from the tenant otherwise.
     *
     * @param string $name
     * @param int $tenantid
     * @return string|null The value, or null when nothing is stored.
     */
    private static function read(string $name, int $tenantid): ?string {
        if ($tenantid === tenant_resolver::NO_TENANT) {
            $value = get_config(self::PLUGIN, $name);
            return $value === false ? null : (string)$value;
        }

        return tenant_config::get_stored(self::PLUGIN, $tenantid, $name);
    }

    /**
     * Store a value: in the site configuration without tenancy, for the tenant otherwise.
     *
     * @param string $name
     * @param string $value
     * @param int $tenantid
     */
    private static function write(string $name, string $value, int $tenantid): void {
        if ($tenantid === tenant_resolver::NO_TENANT) {
            set_config($name, $value, self::PLUGIN);
            return;
        }

        tenant_config::set(self::PLUGIN, $tenantid, $name, $value);
    }

    /**
     * Names of the values kept for a region.
     *
     * @return string[]
     */
    private static function names(): array {
        return [self::REGION, self::FINGERPRINT, self::CHECKED];
    }
}
