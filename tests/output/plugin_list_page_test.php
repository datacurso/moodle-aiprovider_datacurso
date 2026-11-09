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

use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\suite_plugins;

/**
 * Tests for the plugins tab of the provider reports.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\output\plugin_list_page
 * @covers \aiprovider_datacurso\local\suite_plugins::get_overview
 */
final class plugin_list_page_test extends \advanced_testcase {
    /**
     * Each suite plugin is listed with its state; the settings link only when the plugin adds its own fields.
     *
     * S1: the table lists the nine plugins with Enabled and Settings columns.
     */
    public function test_rows_show_state_and_settings_link_only_for_plugins_with_extra_settings(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('plugin_enabled_local_forum_ai', '0', 'aiprovider_datacurso');
        // Forum AI is installed but ships no settings extension: state only, no settings link.
        set_config('version', 2026010100, 'local_forum_ai');
        // Pretend one plugin is not installed, whatever the test site has.
        $DB->delete_records('config_plugins', ['plugin' => 'local_coursegen']);

        $rows = array_column(suite_plugins::get_overview(), null, 'component');

        $this->assertCount(9, $rows);
        $this->assertSame(array_keys(suite_plugins::get_all()), array_keys($rows));

        $coursegen = $rows['local_coursegen'];
        $this->assertFalse($coursegen['installed']);
        $this->assertFalse($coursegen['hassettings']);
        $this->assertSame('', $coursegen['settingsurl']);

        $forumai = $rows['local_forum_ai'];
        $this->assertTrue($forumai['installed']);
        $this->assertFalse($forumai['enabled']);
        $this->assertFalse($forumai['hassettings']);
        $this->assertSame('', $forumai['settingsurl']);

        foreach ($rows as $component => $row) {
            $this->assertSame(get_string(suite_plugins::get($component)['namestring'], 'aiprovider_datacurso'), $row['name']);
            $this->assertSame('https://moodle.org/plugins/' . $component, $row['url']);
            $this->assertSame($component !== 'local_forum_ai', $row['enabled']);
            $this->assertSame($row['installed'] && plugin_settings::has_settings($component), $row['hassettings']);
            if ($row['hassettings']) {
                $expected = new \moodle_url('/ai/provider/datacurso/admin/plugin_settings.php', ['component' => $component]);
                $this->assertSame($expected->out(false), $row['settingsurl']);
            } else {
                $this->assertSame('', $row['settingsurl']);
            }
        }
    }

    /**
     * The renderable exports the rows with the switch context of installed plugins and the link title.
     *
     * S1: template context of the plugins table.
     */
    public function test_export_for_template(): void {
        global $PAGE;

        $installed = [
            'component' => 'local_jedomi',
            'name' => 'Jedomi',
            'description' => 'Desc',
            'url' => 'https://moodle.org/plugins/local_jedomi',
            'installed' => true,
            'enabled' => false,
            'hassettings' => true,
            'settingsurl' => 'https://example.com/settings',
        ];
        $missing = [
            'component' => 'report_lifestory',
            'name' => 'Life Story',
            'description' => 'Desc',
            'url' => 'https://moodle.org/plugins/report_lifestory',
            'installed' => false,
            'enabled' => true,
            'hassettings' => false,
            'settingsurl' => '',
        ];
        $page = new plugin_list_page(['local_jedomi' => $installed, 'report_lifestory' => $missing]);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['hasplugins']);
        $this->assertCount(2, $data['plugins']);

        [$jedomi, $lifestory] = $data['plugins'];
        foreach ($installed as $key => $value) {
            $this->assertSame($value, $jedomi[$key]);
        }
        $this->assertSame(get_string('pluginlink_title', 'aiprovider_datacurso', 'Jedomi'), $jedomi['linktitle']);

        // An installed plugin gets a switch that carries what the JS needs to call the web service.
        $this->assertTrue($jedomi['hastoggle']);
        $toggle = $jedomi['toggle'];
        $this->assertSame('aiprovider_datacurso-plugin-toggle-local_jedomi', $toggle['id']);
        $this->assertFalse($toggle['checked']);
        $this->assertSame(get_string('enableplugin', 'core_admin', 'Jedomi'), $toggle['label']);
        $this->assertSame($toggle['label'], $toggle['title']);
        $this->assertSame('sr-only', $toggle['labelclasses']);
        $this->assertSame([
            ['name' => 'action', 'value' => 'aiprovider_datacurso/plugin_list/toggle-enabled'],
            ['name' => 'component', 'value' => 'local_jedomi'],
            ['name' => 'name', 'value' => 'Jedomi'],
            ['name' => 'state', 'value' => 0],
        ], $toggle['dataattributes']);

        // A plugin that is not installed has no switch.
        $this->assertFalse($lifestory['hastoggle']);
        $this->assertArrayNotHasKey('toggle', $lifestory);
        $this->assertSame(get_string('pluginlink_title', 'aiprovider_datacurso', 'Life Story'), $lifestory['linktitle']);

        $empty = (new plugin_list_page([]))->export_for_template($PAGE->get_renderer('core'));
        $this->assertFalse($empty['hasplugins']);
        $this->assertSame([], $empty['plugins']);
    }

    /**
     * An enabled plugin gets a checked switch labelled "Disable ...".
     *
     * S1: the switch label follows the state, as in the core AI tables.
     */
    public function test_export_for_template_labels_an_enabled_plugin_switch_with_disable(): void {
        global $PAGE;

        $row = [
            'component' => 'local_forum_ai',
            'name' => 'Forum AI',
            'description' => 'Desc',
            'url' => 'https://moodle.org/plugins/local_forum_ai',
            'installed' => true,
            'enabled' => true,
            'hassettings' => false,
            'settingsurl' => '',
        ];
        $data = (new plugin_list_page([$row]))->export_for_template($PAGE->get_renderer('core'));
        $toggle = $data['plugins'][0]['toggle'];

        $this->assertTrue($toggle['checked']);
        $this->assertSame(get_string('disableplugin', 'core_admin', 'Forum AI'), $toggle['label']);
        $this->assertSame(['name' => 'state', 'value' => 1], $toggle['dataattributes'][3]);
    }
}
