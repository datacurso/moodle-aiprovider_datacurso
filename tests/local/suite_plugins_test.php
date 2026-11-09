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

use aiprovider_datacurso\provider;

/**
 * Tests for the single list of Datacurso suite plugins.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\suite_plugins
 */
final class suite_plugins_test extends \advanced_testcase {
    /**
     * Mark Jedomi as installed or not installed on the test site, whatever the codebase has.
     *
     * @param bool $installed Whether Jedomi has a version recorded in config_plugins.
     */
    private function set_jedomi_installed(bool $installed): void {
        global $DB;
        $DB->delete_records('config_plugins', ['plugin' => 'local_jedomi', 'name' => 'version']);
        if ($installed) {
            $DB->insert_record('config_plugins', (object) [
                'plugin' => 'local_jedomi',
                'name' => 'version',
                'value' => '2026010100',
            ]);
        }
    }

    /**
     * With Jedomi installed the list holds the nine suite plugins, each with every field filled in.
     *
     * S1: one list of suite plugins.
     */
    public function test_lists_the_nine_suite_plugins_when_jedomi_is_installed(): void {
        $this->resetAfterTest();
        $this->set_jedomi_installed(true);

        $plugins = suite_plugins::get_all();

        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
            'local_jedomi',
        ], array_keys($plugins));

        $stringmanager = get_string_manager();
        foreach ($plugins as $component => $plugin) {
            $this->assertSame($component, $plugin['component']);
            $this->assertSame($component, $plugin['serviceid']);
            $this->assertSame('https://moodle.org/plugins/' . $component, $plugin['url']);
            $this->assertTrue($stringmanager->string_exists($plugin['namestring'], 'aiprovider_datacurso'));
            $this->assertTrue($stringmanager->string_exists($plugin['descstring'], 'aiprovider_datacurso'));
        }
    }

    /**
     * Jedomi is not published yet: it is left out of the list where it is not installed, the rest stays.
     *
     * S1: unpublished plugins are only listed when installed.
     */
    public function test_jedomi_is_not_listed_when_not_installed(): void {
        $this->resetAfterTest();
        $this->set_jedomi_installed(false);

        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
        ], array_keys(suite_plugins::get_all()));
        $this->assertNotContains('local_jedomi', array_column(suite_plugins::get_overview(), 'component'));

        // It is still a suite plugin, so its settings and name keep working once it is installed.
        $this->assertTrue(suite_plugins::is_suite_plugin('local_jedomi'));
        $this->assertSame('pluginname_jedomi', suite_plugins::get('local_jedomi')['namestring']);
        $this->assertSame('Jedomi', suite_plugins::get_display_name('local_jedomi'));
        $this->assertSame('Jedomi', suite_plugins::get_service_names()['local_jedomi']);
    }

    /**
     * Only components in the list are recognised as suite plugins.
     *
     * S1: the list is the only source of valid components.
     */
    public function test_recognises_only_listed_components(): void {
        $this->assertTrue(suite_plugins::is_suite_plugin('local_jedomi'));
        $this->assertTrue(suite_plugins::is_suite_plugin('report_lifestory'));
        $this->assertFalse(suite_plugins::is_suite_plugin('aiprovider_datacurso'));
        $this->assertFalse(suite_plugins::is_suite_plugin('local_unknown'));
        $this->assertFalse(suite_plugins::is_suite_plugin(''));

        $this->assertSame('pluginname_smartrules', suite_plugins::get('local_coursedynamicrules')['namestring']);
        $this->assertNull(suite_plugins::get('local_unknown'));
    }

    /**
     * The provider services are the suite plugins plus the provider itself, with the same ids and order as before.
     *
     * S1: get_services() keeps its identifiers.
     */
    public function test_services_are_built_from_the_list_with_unchanged_ids(): void {
        $this->resetAfterTest();
        $this->set_jedomi_installed(true);

        $services = provider::get_services();

        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'aiprovider_datacurso',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
            'local_jedomi',
        ], array_column($services, 'id'));

        $names = array_column($services, 'name', 'id');
        $this->assertSame(get_string('pluginname', 'aiprovider_datacurso'), $names['aiprovider_datacurso']);
        foreach (suite_plugins::get_all() as $plugin) {
            $this->assertSame(
                get_string($plugin['namestring'], 'aiprovider_datacurso'),
                $names[$plugin['serviceid']]
            );
        }
    }

    /**
     * Jedomi is not a provider service where it is not installed; the other ids keep their order.
     *
     * S1: get_services() follows the listed plugins.
     */
    public function test_services_leave_out_jedomi_when_not_installed(): void {
        $this->resetAfterTest();
        $this->set_jedomi_installed(false);

        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'aiprovider_datacurso',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
        ], array_column(provider::get_services(), 'id'));
    }

    /**
     * A plugin is installed when its version is recorded in config_plugins.
     *
     * S1: the plugins tab and the switch service share one notion of "installed".
     */
    public function test_is_installed_follows_config_plugins(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->delete_records('config_plugins', ['plugin' => 'local_coursegen']);
        $this->assertFalse(suite_plugins::is_installed('local_coursegen'));

        set_config('version', 2026010100, 'local_coursegen');
        $this->assertTrue(suite_plugins::is_installed('local_coursegen'));

        $this->assertFalse(suite_plugins::is_installed('local_unknown'));
    }
}
