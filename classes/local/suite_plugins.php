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
 * Single list of the Datacurso suite plugins that use this provider.
 *
 * The plugins tab, the per-plugin settings page and provider::get_services() read this list, so a
 * plugin is added or removed in one place. The service id is the one the rate limits and the
 * consumption report already use, which today is the component name of each plugin.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class suite_plugins {
    /** @var string Base URL of the plugin pages in the moodle.org plugins directory. */
    private const DIRECTORY_URL = 'https://moodle.org/plugins/';

    /**
     * Suite plugins in display order: component => [name string id, description string id].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PLUGINS = [
        'local_coursegen' => ['pluginname_coursegen', 'plugindesc_coursegen'],
        'local_datacurso_ratings' => ['pluginname_datacurso_ratings', 'plugindesc_datacurso_ratings'],
        'local_forum_ai' => ['pluginname_forum_ai', 'plugindesc_forum_ai'],
        'local_assign_ai' => ['pluginname_assign_ai', 'plugindesc_assign_ai'],
        'local_dttutor' => ['pluginname_dttutor', 'plugindesc_dttutor'],
        'local_socialcert' => ['pluginname_socialcert', 'plugindesc_socialcert'],
        'report_lifestory' => ['pluginname_lifestory', 'plugindesc_lifestory'],
        'local_coursedynamicrules' => ['pluginname_smartrules', 'plugindesc_smartrules'],
        'local_jedomi' => ['pluginname_jedomi', 'plugindesc_jedomi'],
    ];

    /**
     * All suite plugins, keyed by component, in display order.
     *
     * @return array<string, array{component: string, namestring: string, descstring: string, url: string, serviceid: string}>
     */
    public static function get_all(): array {
        $plugins = [];
        foreach (self::PLUGINS as $component => [$namestring, $descstring]) {
            $plugins[$component] = [
                'component' => $component,
                'namestring' => $namestring,
                'descstring' => $descstring,
                'url' => self::DIRECTORY_URL . $component,
                'serviceid' => $component,
            ];
        }
        return $plugins;
    }

    /**
     * One suite plugin, or null when the component is not in the list.
     *
     * @param string $component Frankenstyle component name.
     * @return array{component: string, namestring: string, descstring: string, url: string, serviceid: string}|null
     */
    public static function get(string $component): ?array {
        return self::get_all()[$component] ?? null;
    }

    /**
     * Whether a component is one of the suite plugins.
     *
     * @param string $component Frankenstyle component name.
     * @return bool
     */
    public static function is_suite_plugin(string $component): bool {
        return array_key_exists($component, self::PLUGINS);
    }

    /**
     * Whether a suite plugin is installed on this site.
     *
     * @param string $component Frankenstyle component name.
     * @return bool True when the plugin has a version recorded in config_plugins.
     */
    public static function is_installed(string $component): bool {
        global $DB;
        return $DB->record_exists('config_plugins', ['plugin' => $component, 'name' => 'version']);
    }

    /**
     * Rows of the plugins tab: name, description, moodle.org link, installed, enabled and settings link.
     *
     * The settings link is only offered for installed plugins that add their own fields to the settings
     * page; a plugin with nothing beyond "Enabled" is switched from the tab itself.
     *
     * @return array<int, array{component: string, name: string, description: string, url: string, installed: bool,
     *     enabled: bool, hassettings: bool, settingsurl: string}>
     */
    public static function get_overview(): array {
        $rows = [];
        foreach (self::get_all() as $component => $plugin) {
            $installed = self::is_installed($component);
            $hassettings = $installed && plugin_settings::has_settings($component);
            $settingsurl = $hassettings
                ? (new \moodle_url('/ai/provider/datacurso/admin/plugin_settings.php', ['component' => $component]))->out(false)
                : '';
            $rows[] = [
                'component' => $component,
                'name' => get_string($plugin['namestring'], 'aiprovider_datacurso'),
                'description' => get_string($plugin['descstring'], 'aiprovider_datacurso'),
                'url' => $plugin['url'],
                'installed' => $installed,
                'enabled' => plugin_settings::is_enabled($component),
                'hassettings' => $hassettings,
                'settingsurl' => $settingsurl,
            ];
        }
        return $rows;
    }
}
