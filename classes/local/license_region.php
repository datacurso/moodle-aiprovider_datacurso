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
     * Not about the licence changing, which the fingerprint sees on the very next request: about
     * this side finding out when the region of a licence is moved without the key changing.
     */
    public const TTL = WEEKSECS;

    /**
     * Whether the licence of this site belongs to the European deployment.
     *
     * @return bool
     * @throws \moodle_exception When the region is not known and the shop cannot be asked.
     */
    public static function is_european(): bool {
        $fingerprint = self::fingerprint();
        $stored = get_config(self::PLUGIN, self::REGION);
        $known = self::is_known($stored, $fingerprint);

        if ($known && self::is_fresh()) {
            return (bool)$stored;
        }

        try {
            $european = static::ask_the_shop();
        } catch (\moodle_exception $e) {
            return self::fall_back($e, $known, $stored);
        }

        self::remember($european, $fingerprint);

        return $european;
    }

    /**
     * Ask the shop which region this licence belongs to.
     *
     * Kept as a separate step so tests can answer for the shop without touching the network.
     *
     * @return bool
     * @throws \moodle_exception When the shop cannot be reached or the licence is not configured.
     */
    protected static function ask_the_shop(): bool {
        $api = new datacurso_api();
        $response = $api->get('tokens/saldo');

        return !empty($response['is_for_eu']);
    }

    /**
     * What to answer when the shop could not be asked.
     *
     * @param \moodle_exception $failure What the shop failed with.
     * @param bool $known Whether the region of this licence was resolved before.
     * @param mixed $stored The region resolved before, as the configuration holds it.
     * @return bool
     * @throws \moodle_exception When nothing is known, because guessing would send the data of a
     *                           site to a region it may not be allowed to reach.
     */
    private static function fall_back(\moodle_exception $failure, bool $known, $stored): bool {
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
     * Whether the region held in the configuration belongs to the licence in force.
     *
     * @param mixed $stored The region held, as the configuration holds it.
     * @param string $fingerprint Fingerprint of the licence in force.
     * @return bool
     */
    private static function is_known($stored, string $fingerprint): bool {
        if ($stored === false) {
            return false;
        }
        $knownfor = get_config(self::PLUGIN, self::FINGERPRINT);

        return $knownfor === $fingerprint;
    }

    /**
     * Whether the region held in the configuration is recent enough to be trusted.
     *
     * @return bool
     */
    private static function is_fresh(): bool {
        $checked = get_config(self::PLUGIN, self::CHECKED);
        $age = time() - (int)$checked;

        return $age < self::TTL;
    }

    /**
     * Keep the region, and the licence it belongs to, for the requests that follow.
     *
     * @param bool $european Whether the licence belongs to the European deployment.
     * @param string $fingerprint Fingerprint of the licence it was resolved for.
     */
    private static function remember(bool $european, string $fingerprint): void {
        if ($european) {
            $region = '1';
        } else {
            $region = '0';
        }
        set_config(self::REGION, $region, self::PLUGIN);
        set_config(self::FINGERPRINT, $fingerprint, self::PLUGIN);
        set_config(self::CHECKED, time(), self::PLUGIN);
    }

    /**
     * A fingerprint of the licence in force, so a new licence resolves its own region.
     *
     * The key lives in the configuration of the AI provider instance, which core saves without
     * telling the plugin; comparing fingerprints is what notices a new key. What is kept here is
     * only enough to tell one licence from another.
     *
     * @return string
     */
    private static function fingerprint(): string {
        $key = trim(self::licence_key());
        if ($key === '') {
            return '';
        }

        return sha1($key);
    }

    /**
     * The licence key of the enabled Datacurso provider instance, the same one the clients send.
     *
     * @return string Empty when no enabled instance holds a key.
     */
    private static function licence_key(): string {
        global $DB;

        $manager = new \core_ai\manager($DB);
        foreach ($manager->get_provider_instances() as $instance) {
            if ($instance->get_name() !== self::PLUGIN || $instance->enabled !== true) {
                continue;
            }
            if (!empty($instance->config['licensekey'])) {
                return (string)$instance->config['licensekey'];
            }
        }

        return '';
    }
}
