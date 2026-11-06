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
 * State of each suite plugin, kept by the provider.
 *
 * Each suite plugin asks is_enabled() instead of reading its own "Enabled" setting. The state lives in
 * `aiprovider_datacurso/plugin_enabled_<component>`; without a saved value the plugin is enabled.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_settings {
    /** @var string Prefix of the provider setting that holds the state of a plugin. */
    public const CONFIG_PREFIX = 'plugin_enabled_';

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
        'local_socialcert' => ['enableai', false],
        'local_jedomi' => ['enabled', true],
    ];

    /**
     * Whether a suite plugin is enabled in the provider.
     *
     * @param string $component Frankenstyle component name, e.g. 'local_forum_ai'.
     * @return bool True when no state is saved or the saved state is enabled.
     */
    public static function is_enabled(string $component): bool {
        $value = get_config('aiprovider_datacurso', self::CONFIG_PREFIX . $component);
        return $value === false || !empty($value);
    }

    /**
     * Stop the request when a suite plugin is disabled in the provider.
     *
     * Suite plugins call it at the top of their pages and web services. A site administrator is told
     * where to enable the plugin and gets a Continue link to the plugins list; anyone else is told to
     * contact the administrator.
     *
     * @param string $component Frankenstyle component name, e.g. 'local_forum_ai'.
     * @return void
     * @throws \moodle_exception plugindisabled or plugindisabled_admin when the plugin is disabled.
     */
    public static function require_enabled(string $component): void {
        if (self::is_enabled($component)) {
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
     * @param string $component Frankenstyle component name of a suite plugin.
     * @param bool $enabled New state.
     * @return void
     * @throws \coding_exception When the component is not a suite plugin.
     */
    public static function set_enabled(string $component, bool $enabled): void {
        if (!suite_plugins::is_suite_plugin($component)) {
            throw new \coding_exception("'{$component}' is not a Datacurso suite plugin");
        }
        $name = self::CONFIG_PREFIX . $component;
        $value = $enabled ? '1' : '0';
        $previous = get_config('aiprovider_datacurso', $name);
        $changed = $previous === false || (string) $previous !== $value;
        set_config($name, $value, 'aiprovider_datacurso', $changed);
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
     * Copy the former "Enabled" setting of each plugin into the provider, once.
     *
     * Used by the upgrade so that no site changes state when the plugins drop their own setting.
     * A plugin without the setting (not installed, or one that never had it) stays enabled, and a
     * state already saved in the provider is never overwritten. Safe to run more than once.
     *
     * @return void
     */
    public static function migrate_legacy_flags(): void {
        foreach (self::LEGACY_FLAGS as $component => [$name, $onlyzerodisables]) {
            if (get_config('aiprovider_datacurso', self::CONFIG_PREFIX . $component) !== false) {
                continue;
            }
            $value = get_config($component, $name);
            if ($value === false) {
                continue;
            }
            $enabled = $onlyzerodisables ? ((string) $value !== '0') : !empty($value);
            set_config(self::CONFIG_PREFIX . $component, $enabled ? '1' : '0', 'aiprovider_datacurso');
        }
    }
}
