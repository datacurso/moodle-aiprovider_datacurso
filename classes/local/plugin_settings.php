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
 * State of each suite plugin, kept by the provider per tenant.
 *
 * Each suite plugin asks is_enabled() instead of reading its own "Enabled" setting. The state lives in
 * `plugin_enabled_<component>`: a tenant keeps its own value in the tenant configuration table, as it does
 * for the rate limits, and a tenant that has saved none inherits the site default, kept in
 * `aiprovider_datacurso/plugin_enabled_<component>`. Without any saved value the plugin is enabled.
 *
 * On a site without tenancy there is a single implicit tenant (tenant_resolver::NO_TENANT), whose state is
 * the site default.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_settings {
    /** @var string Prefix of the provider setting that holds the state of a plugin. */
    public const CONFIG_PREFIX = 'plugin_enabled_';

    /** @var string Component that owns the settings. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Former "Enabled" setting of each plugin that had one: component => [setting name, only '0' disables].
     *
     * Jedomi treated only '0' as disabled; the other plugins treated any empty value as disabled.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    private const LEGACY_FLAGS = [
        'local_forum_ai' => ['enableforumai', false],
        'local_assign_ai' => ['enableassignai', false],
        'local_dttutor' => ['enabled', false],
        'local_datacurso_ratings' => ['enabled', false],
        // Not local_socialcert: on Workplace its enableai only switches the AI assistant of a tenant off.
        'local_jedomi' => ['enabled', true],
    ];

    /**
     * Whether a suite plugin is enabled in the provider for a tenant.
     *
     * The value saved for the tenant wins; without one the site default applies; without a site default the
     * plugin is enabled.
     *
     * @param string $component Frankenstyle component name, e.g. 'local_forum_ai'.
     * @param int|null $tenantid Tenant to check; null checks the tenant of the current user.
     * @return bool True when no state is saved or the saved state is enabled.
     */
    public static function is_enabled(string $component, ?int $tenantid = null): bool {
        $tenantid = self::resolve_tenant($tenantid);
        $name = self::CONFIG_PREFIX . $component;
        if ($tenantid === tenant_resolver::NO_TENANT) {
            $value = get_config(self::PLUGIN, $name);
        } else {
            $value = tenant_config::get(self::PLUGIN, $tenantid, $name, get_config(self::PLUGIN, $name));
        }
        return $value === false || $value === null || !empty($value);
    }

    /**
     * Stop the request when a suite plugin is disabled in the provider for a tenant.
     *
     * Suite plugins call it at the top of their pages and web services. A site administrator is told
     * where to enable the plugin and gets a Continue link to the plugins list; anyone else is told to
     * contact the administrator.
     *
     * @param string $component Frankenstyle component name, e.g. 'local_forum_ai'.
     * @param int|null $tenantid Tenant to check; null checks the tenant of the current user.
     * @return void
     * @throws \moodle_exception plugindisabled or plugindisabled_admin when the plugin is disabled.
     */
    public static function require_enabled(string $component, ?int $tenantid = null): void {
        if (self::is_enabled($component, $tenantid)) {
            return;
        }
        $name = suite_plugins::get_display_name($component);
        if (has_capability('moodle/site:config', \context_system::instance())) {
            $url = new \moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'pluginslist']);
            $a = (object) ['name' => $name, 'url' => $url->out()];
            throw new \moodle_exception('plugindisabled_admin', 'aiprovider_datacurso', $url, $a);
        }
        throw new \moodle_exception('plugindisabled', 'aiprovider_datacurso', '', $name);
    }

    /**
     * Enable or disable a suite plugin, writing a config_log row when the state changes.
     *
     * Without a tenant (or with tenant_resolver::NO_TENANT) the site default is written. With a tenant the
     * value is written for that tenant only, and its config_log row names the tenant.
     *
     * @param string $component Frankenstyle component name of a suite plugin.
     * @param bool $enabled New state.
     * @param int|null $tenantid Tenant to write the state for; null writes the site default.
     * @return void
     * @throws \coding_exception When the component is not a suite plugin.
     */
    public static function set_enabled(string $component, bool $enabled, ?int $tenantid = null): void {
        if (!suite_plugins::is_suite_plugin($component)) {
            throw new \coding_exception("'{$component}' is not a Datacurso suite plugin");
        }
        $name = self::CONFIG_PREFIX . $component;
        $value = $enabled ? '1' : '0';

        if ($tenantid === null || $tenantid === tenant_resolver::NO_TENANT) {
            $previous = get_config(self::PLUGIN, $name);
            $changed = $previous === false || (string) $previous !== $value;
            set_config($name, $value, self::PLUGIN, $changed);
            return;
        }

        $previous = tenant_config::get_stored(self::PLUGIN, $tenantid, $name);
        if ($previous === $value) {
            return;
        }
        tenant_config::set(self::PLUGIN, $tenantid, $name, $value);
        add_to_config_log(self::tenant_log_name($name, $tenantid), $previous, $value, self::PLUGIN);
    }

    /**
     * Name under which a tenant change is written to the config log.
     *
     * @param string $name Setting name.
     * @param int $tenantid Tenant id.
     * @return string
     */
    public static function tenant_log_name(string $name, int $tenantid): string {
        return "{$name} (tenant {$tenantid})";
    }

    /**
     * Tenant whose state applies: the given one, or the tenant of the current user.
     *
     * @param int|null $tenantid Tenant id, or null for the tenant of the current user.
     * @return int
     */
    public static function resolve_tenant(?int $tenantid = null): int {
        return $tenantid ?? tenant_resolver::get_tenant_id();
    }

    /**
     * Whether the current user may switch suite plugins on and off.
     *
     * Same permission as the tenant configuration page (moodle/site:config), or the provider's own
     * aiprovider/datacurso:manageconfig.
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return has_any_capability(
            ['aiprovider/datacurso:manageconfig', 'moodle/site:config'],
            \context_system::instance()
        );
    }

    /**
     * Stop unless the current user may switch suite plugins on and off.
     *
     * @return void
     * @throws \required_capability_exception When the user has neither capability.
     */
    public static function require_manage_capability(): void {
        if (!self::can_manage()) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'aiprovider/datacurso:manageconfig',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Whether the current user may manage the state of a scope.
     *
     * A tenant administrator manages only their own tenant. A site administrator also manages the site
     * default (tenant_resolver::NO_TENANT) and any other tenant. On a site without tenancy the only scope
     * is the site default.
     *
     * @param int $tenantid Scope: a tenant id, or tenant_resolver::NO_TENANT for the site default.
     * @return bool
     */
    public static function can_manage_scope(int $tenantid): bool {
        if (!self::can_manage()) {
            return false;
        }
        return is_siteadmin() || $tenantid === tenant_resolver::get_tenant_id();
    }

    /**
     * Stop unless the current user may manage the state of a scope, and return the scope.
     *
     * @param int|null $tenantid Scope: a tenant id, tenant_resolver::NO_TENANT for the site default, or null
     *     for the tenant of the current user.
     * @return int The scope to manage.
     * @throws \required_capability_exception When the user cannot manage the plugins at all.
     * @throws \moodle_exception When the scope belongs to another tenant.
     */
    public static function require_manage_scope(?int $tenantid = null): int {
        self::require_manage_capability();
        $tenantid = self::resolve_tenant($tenantid);
        if (!self::can_manage_scope($tenantid)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginscope', 'aiprovider_datacurso'));
        }
        return $tenantid;
    }

    /**
     * Settings extension shipped by a plugin, if any.
     *
     * The class `\<component>\aiprovider\settings_extension` is used only when it implements
     * plugin_settings_extension, so the provider never references the plugin's classes directly.
     *
     * @param string $component Frankenstyle component name.
     * @return plugin_settings_extension|null
     */
    public static function get_extension(string $component): ?plugin_settings_extension {
        $classname = '\\' . $component . '\\aiprovider\\settings_extension';
        if (!class_exists($classname) || !is_subclass_of($classname, plugin_settings_extension::class)) {
            return null;
        }
        return new $classname();
    }

    /**
     * Whether a plugin has settings beyond "Enabled", that is, whether it ships a settings extension.
     *
     * @param string $component Frankenstyle component name.
     * @return bool
     */
    public static function has_settings(string $component): bool {
        return self::get_extension($component) !== null;
    }

    /**
     * Copy the former "Enabled" setting of each plugin into the provider's site default, once.
     *
     * Used by the upgrade so that no site changes state when the plugins drop their own setting.
     * A plugin without the setting (not installed, or one that never had it) stays enabled, and a
     * state already saved in the provider is never overwritten. Safe to run more than once.
     *
     * Only the site default is seeded. Each suite plugin copies its own per-tenant values in its own
     * upgrade, through set_enabled() with the tenant id.
     *
     * @return void
     */
    public static function migrate_legacy_flags(): void {
        foreach (self::LEGACY_FLAGS as $component => [$name, $onlyzerodisables]) {
            if (get_config(self::PLUGIN, self::CONFIG_PREFIX . $component) !== false) {
                continue;
            }
            $value = get_config($component, $name);
            if ($value === false) {
                continue;
            }
            $enabled = $onlyzerodisables ? ((string) $value !== '0') : !empty($value);
            set_config(self::CONFIG_PREFIX . $component, $enabled ? '1' : '0', self::PLUGIN);
        }
    }
}
