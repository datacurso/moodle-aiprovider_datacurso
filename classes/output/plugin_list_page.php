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

namespace aiprovider_datacurso\output;

use renderable;
use templatable;
use renderer_base;

/**
 * Plugins tab of the provider reports: the Datacurso suite plugins with their state.
 *
 * Provides the context for the aiprovider_datacurso/plugin_list_page template. The rows come from
 * \aiprovider_datacurso\local\suite_plugins::get_overview().
 *
 * @package    aiprovider_datacurso
 * @category   output
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_list_page implements renderable, templatable {
    /**
     * Rows of the table.
     *
     * @var array<int|string, array{component: string, name: string, description: string, url: string, installed: bool,
     *     enabled: bool, hassettings: bool, settingsurl: string}>
     */
    private array $plugins;

    /**
     * Constructor.
     *
     * @param array $plugins Rows of the table, as returned by suite_plugins::get_overview().
     */
    public function __construct(array $plugins) {
        $this->plugins = $plugins;
    }

    /**
     * Export data for the Mustache template.
     *
     * Each row gains `linktitle`, the accessible name of the moodle.org link, and, for installed plugins,
     * `toggle`: the context of the core/toggle switch that enables or disables the plugin from the tab.
     * The switch label follows the state ("Enable X" / "Disable X") as in the core AI tables.
     *
     * @param renderer_base $output The renderer instance.
     * @return array{plugins: array, hasplugins: bool} Data to be used in the template.
     */
    public function export_for_template(renderer_base $output): array {
        $plugins = [];
        foreach (array_values($this->plugins) as $row) {
            $row['linktitle'] = get_string('pluginlink_title', 'aiprovider_datacurso', $row['name']);
            $row['hastoggle'] = $row['installed'];
            if ($row['installed']) {
                $row['toggle'] = self::toggle_context($row['component'], $row['name'], $row['enabled']);
            }
            $plugins[] = $row;
        }
        return [
            'plugins' => $plugins,
            'hasplugins' => !empty($plugins),
        ];
    }

    /**
     * Context of the core/toggle switch of one plugin.
     *
     * @param string $component Frankenstyle component name.
     * @param string $name Display name of the plugin.
     * @param bool $enabled Current state.
     * @return array{id: string, checked: bool, dataattributes: array, title: string, label: string, labelclasses: string}
     */
    private static function toggle_context(string $component, string $name, bool $enabled): array {
        $label = get_string($enabled ? 'disableplugin' : 'enableplugin', 'core_admin', $name);
        return [
            'id' => 'aiprovider_datacurso-plugin-toggle-' . $component,
            'checked' => $enabled,
            'dataattributes' => [
                ['name' => 'action', 'value' => 'aiprovider_datacurso/plugin_list/toggle-enabled'],
                ['name' => 'component', 'value' => $component],
                ['name' => 'name', 'value' => $name],
                ['name' => 'state', 'value' => $enabled ? 1 : 0],
            ],
            'title' => $label,
            'label' => $label,
            'labelclasses' => 'sr-only',
        ];
    }
}
