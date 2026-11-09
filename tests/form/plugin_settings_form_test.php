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

namespace aiprovider_datacurso\form;

use aiprovider_datacurso\local\plugin_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/ai/provider/datacurso/tests/fixtures/fake_settings_extension.php');

/**
 * Tests for the per-plugin settings form of the provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\plugin_settings_form
 */
final class plugin_settings_form_test extends \advanced_testcase {
    /**
     * A component that is not a suite plugin is rejected.
     *
     * S3: the settings page only accepts listed components.
     */
    public function test_require_suite_plugin_rejects_components_outside_the_list(): void {
        plugin_settings_form::require_suite_plugin('local_forum_ai');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsuiteplugin', 'aiprovider_datacurso', 'mod_forum'));
        plugin_settings_form::require_suite_plugin('mod_forum');
    }

    /**
     * Saving refuses a component outside the list even for an administrator.
     *
     * S3: no setting is written for unknown components.
     */
    public function test_save_rejects_components_outside_the_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        try {
            plugin_settings_form::save('local_unknown', (object) ['enabled' => 0]);
            $this->fail('An unknown component must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsuiteplugin', $e->errorcode);
        }
        $this->assertFalse(get_config('aiprovider_datacurso', 'plugin_enabled_local_unknown'));
    }

    /**
     * A user who can only view the reports cannot change the state of a plugin.
     *
     * S3: manageconfig is required.
     */
    public function test_save_requires_manageconfig(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiprovider/datacurso:viewreports', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());
        $this->setUser($user);

        try {
            plugin_settings_form::save('local_forum_ai', (object) ['enabled' => 0]);
            $this->fail('A user without manageconfig must not change the state.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
            $this->assertStringContainsString(
                get_capability_string('aiprovider/datacurso:manageconfig'),
                $e->getMessage()
            );
        }
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));
    }

    /**
     * Saving the form changes the state of the plugin, leaves a config_log row and reloads the saved value.
     *
     * S3: saving changes the state and is audited.
     */
    public function test_save_changes_the_state_and_is_audited(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertSame(['component' => 'local_dttutor', 'enabled' => 1], plugin_settings_form::current_data('local_dttutor'));

        plugin_settings_form::save('local_dttutor', (object) ['enabled' => 0]);

        $this->assertFalse(plugin_settings::is_enabled('local_dttutor'));
        $this->assertSame(0, plugin_settings_form::current_data('local_dttutor')['enabled']);
        $rows = array_values($DB->get_records(
            'config_log',
            ['plugin' => 'aiprovider_datacurso', 'name' => 'plugin_enabled_local_dttutor'],
            'id DESC'
        ));
        $this->assertNotEmpty($rows);
        $this->assertSame('0', $rows[0]->value);
    }

    /**
     * The form shows "Enabled" for every plugin and the fields and validation of the plugin's extension.
     *
     * S5: the page uses the extension of the plugin when it exists.
     */
    public function test_form_adds_extension_fields_and_validation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new plugin_settings_form(null, ['component' => 'local_fakesuite']);
        $html = $form->render();
        $this->assertStringContainsString('name="enabled"', $html);
        $this->assertStringContainsString('name="fakefield"', $html);

        $this->assertSame(['fakefield' => 'Bad value'], $form->validation(['fakefield' => 'bad'], []));
        $this->assertSame([], $form->validation(['fakefield' => 'good'], []));

        // A plugin without extension only has the "Enabled" field.
        $plain = new plugin_settings_form(null, ['component' => 'local_forum_ai']);
        $html = $plain->render();
        $this->assertStringContainsString('name="enabled"', $html);
        $this->assertStringNotContainsString('name="fakefield"', $html);
        $this->assertSame([], $plain->validation(['enabled' => 1], []));
    }
}
