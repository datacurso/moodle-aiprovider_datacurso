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

/**
 * Whether the provider may send requests to the Datacurso AI services, per tenant.
 *
 * The "Enabled" switch of Site administration > AI > AI providers is the site switch: with it off no
 * request of any tenant leaves the site. On Moodle Workplace a tenant may also switch the provider off
 * for itself, kept as `provider_enabled` in the tenant configuration, as the licence and the rate limits
 * are. A tenant that has saved no value follows the site switch, so nothing changes on upgrade.
 *
 * The shop client (balance, consumption history) does not ask it, so the reports keep working.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_status {
    /** @var string Name of the tenant setting that switches the provider off for a tenant. */
    public const CONFIG_NAME = 'provider_enabled';

    /** @var string Component that owns the settings. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Whether the site switch of the provider is on.
     *
     * @return bool
     */
    public static function is_site_enabled(): bool {
        return \core\plugininfo\aiprovider::is_plugin_enabled('datacurso');
    }

    /**
     * Whether a tenant has switched the provider off for itself.
     *
     * On a site without tenancy the single implicit tenant (tenant_resolver::NO_TENANT) is the one the
     * configuration page saves for and the clients read, so its switch applies to the whole site.
     *
     * @param int $tenantid Tenant id.
     * @return bool
     */
    public static function is_tenant_disabled(int $tenantid): bool {
        return tenant_config::get_stored(self::PLUGIN, $tenantid, self::CONFIG_NAME) === '0';
    }

    /**
     * Whether the provider may send requests for a tenant: the site switch and the tenant switch are on.
     *
     * @param int|null $tenantid Tenant to check; null checks the tenant of the current user.
     * @return bool
     */
    public static function is_enabled(?int $tenantid = null): bool {
        $tenantid = $tenantid ?? tenant_resolver::get_tenant_id();
        return self::is_site_enabled() && !self::is_tenant_disabled($tenantid);
    }

    /**
     * Stop a request that must not leave the site because the provider is switched off.
     *
     * @param int|null $tenantid Tenant the request is made for; null uses the tenant of the current user.
     * @return void
     * @throws \moodle_exception providerdisabled (site switch) or providerdisabled_tenant (tenant switch).
     */
    public static function require_enabled(?int $tenantid = null): void {
        $errorcode = self::get_disabled_errorcode($tenantid);
        if ($errorcode !== null) {
            throw new \moodle_exception($errorcode, self::PLUGIN);
        }
    }

    /**
     * Error code that says why the provider is switched off for a tenant, or null when it is on.
     *
     * @param int|null $tenantid Tenant to check; null checks the tenant of the current user.
     * @return string|null providerdisabled, providerdisabled_tenant or null.
     */
    public static function get_disabled_errorcode(?int $tenantid = null): ?string {
        if (!self::is_site_enabled()) {
            return 'providerdisabled';
        }
        $tenantid = $tenantid ?? tenant_resolver::get_tenant_id();
        return self::is_tenant_disabled($tenantid) ? 'providerdisabled_tenant' : null;
    }

    /**
     * Switch the provider on or off for a tenant, writing a config_log row when the state changes.
     *
     * @param int $tenantid Tenant id.
     * @param bool $enabled New state.
     * @return void
     */
    public static function set_tenant_enabled(int $tenantid, bool $enabled): void {
        $value = $enabled ? '1' : '0';
        $previous = tenant_config::get_stored(self::PLUGIN, $tenantid, self::CONFIG_NAME);
        if ($previous === $value || ($previous === null && $enabled)) {
            return;
        }
        tenant_config::set(self::PLUGIN, $tenantid, self::CONFIG_NAME, $value);
        add_to_config_log(plugin_settings::tenant_log_name(self::CONFIG_NAME, $tenantid), $previous, $value, self::PLUGIN);
    }
}
