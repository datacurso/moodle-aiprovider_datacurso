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

use core\output\single_select;
use renderable;
use templatable;
use renderer_base;

/**
 * Plugins tab of the provider reports: the Datacurso suite plugins with their state.
 *
 * Provides the context for the aiprovider_datacurso/plugin_list_page template. The rows come from
 * \aiprovider_datacurso\local\suite_plugins::get_overview() for one scope: a tenant, or the site default.
 * On Moodle Workplace the page also says which scope the switches change and, for site administrators,
 * offers a selector to move between the site default and their tenant.
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

    /** @var int|null Scope the switches change: a tenant id, 0 for the site default, null for the user's tenant. */
    private ?int $tenantid;

    /** @var string Notice that says which scope the switches change; empty for none. */
    private string $scopenotice;

    /** @var single_select|null Selector of the scope, offered to site administrators on Moodle Workplace. */
    private ?single_select $scopeselector;

    /** @var bool Whether the current user may switch the plugins on and off. */
    private bool $canmanage;

    /**
     * Constructor.
     *
     * @param array $plugins Rows of the table, as returned by suite_plugins::get_overview().
     * @param int|null $tenantid Scope the switches change; null lets the web service use the user's tenant.
     * @param string $scopenotice Notice that says which scope the switches change; empty for none.
     * @param single_select|null $scopeselector Selector of the scope, or null when there is no choice.
     * @param bool $canmanage Whether the current user may switch the plugins on and off.
     */
    public function __construct(
        array $plugins,
        ?int $tenantid = null,
        string $scopenotice = '',
        ?single_select $scopeselector = null,
        bool $canmanage = true
    ) {
        $this->plugins = $plugins;
        $this->tenantid = $tenantid;
        $this->scopenotice = $scopenotice;
        $this->scopeselector = $scopeselector;
        $this->canmanage = $canmanage;
    }

    /**
     * Export data for the Mustache template.
     *
     * Each row gains `linktitle`, the accessible name of the moodle.org link, and, for installed plugins
     * when the user may manage them, `toggle`: the context of the core/toggle switch that enables or
     * disables the plugin from the tab. The switch label follows the state ("Enable X" / "Disable X") as in
     * the core AI tables, and the switch carries the scope it changes.
     *
     * @param renderer_base $output The renderer instance.
     * @return array{plugins: array, hasplugins: bool, scopenotice: string, scopeselector: string} Template data.
     */
    public function export_for_template(renderer_base $output): array {
        $plugins = [];
        foreach (array_values($this->plugins) as $row) {
            $row['linktitle'] = get_string('pluginlink_title', 'aiprovider_datacurso', $row['name']);
            $row['hastoggle'] = $row['installed'] && $this->canmanage;
            if ($row['hastoggle']) {
                $row['toggle'] = self::toggle_context($row['component'], $row['name'], $row['enabled'], $this->tenantid);
            }
            $plugins[] = $row;
        }
        return [
            'plugins' => $plugins,
            'hasplugins' => !empty($plugins),
            'scopenotice' => $this->scopenotice,
            'scopeselector' => $this->scopeselector !== null ? $output->render($this->scopeselector) : '',
        ];
    }

    /**
     * Context of the core/toggle switch of one plugin.
     *
     * @param string $component Frankenstyle component name.
     * @param string $name Display name of the plugin.
     * @param bool $enabled Current state.
     * @param int|null $tenantid Scope the switch changes; null adds no tenant attribute.
     * @return array{id: string, checked: bool, dataattributes: array, title: string, label: string, labelclasses: string}
     */
    private static function toggle_context(string $component, string $name, bool $enabled, ?int $tenantid): array {
        $label = get_string($enabled ? 'disableplugin' : 'enableplugin', 'core_admin', $name);
        $dataattributes = [
            ['name' => 'action', 'value' => 'aiprovider_datacurso/plugin_list/toggle-enabled'],
            ['name' => 'component', 'value' => $component],
            ['name' => 'name', 'value' => $name],
            ['name' => 'state', 'value' => $enabled ? 1 : 0],
        ];
        if ($tenantid !== null) {
            $dataattributes[] = ['name' => 'tenantid', 'value' => $tenantid];
        }
        return [
            'id' => 'aiprovider_datacurso-plugin-toggle-' . $component,
            'checked' => $enabled,
            'dataattributes' => $dataattributes,
            'title' => $label,
            'label' => $label,
            'labelclasses' => 'sr-only',
        ];
    }
}
