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

namespace aiprovider_datacurso\external;

use aiprovider_datacurso\local\plugin_settings;

/**
 * Tests for the web service behind the "Enabled" switch of the plugins tab.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\external\set_plugin_enabled
 */
final class set_plugin_enabled_test extends \advanced_testcase {
    /**
     * Mark a suite plugin as installed on the test site, whatever the site has.
     *
     * @param string $component Frankenstyle component name.
     * @return void
     */
    private function mark_installed(string $component): void {
        set_config('version', 2026010100, $component);
    }

    /**
     * The switch disables and enables an installed plugin, and the answer carries the new state.
     *
     * S6: the state changes without leaving the plugins tab.
     */
    public function test_execute_toggles_the_state_of_an_installed_plugin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->mark_installed('local_forum_ai');

        $result = set_plugin_enabled::execute('local_forum_ai', false);
        $result = \core_external\external_api::clean_returnvalue(set_plugin_enabled::execute_returns(), $result);

        $this->assertFalse($result['enabled']);
        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai'));
        $this->assertSame('0', get_config('aiprovider_datacurso', 'plugin_enabled_local_forum_ai'));

        $result = set_plugin_enabled::execute('local_forum_ai', true);
        $result = \core_external\external_api::clean_returnvalue(set_plugin_enabled::execute_returns(), $result);

        $this->assertTrue($result['enabled']);
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));

        // The change is confirmed with a notification, as the core AI tables do.
        $notifications = \core\notification::fetch();
        $this->assertCount(2, $notifications);
        $this->assertStringContainsString(
            get_string('plugin_disabled', 'core_admin', get_string('pluginname_forum_ai', 'aiprovider_datacurso')),
            $notifications[0]->get_message()
        );
        $this->assertStringContainsString(
            get_string('plugin_enabled', 'core_admin', get_string('pluginname_forum_ai', 'aiprovider_datacurso')),
            $notifications[1]->get_message()
        );
    }

    /**
     * A user who can only view the reports cannot flip the switch.
     *
     * S6: manageconfig is required.
     */
    public function test_execute_requires_manageconfig(): void {
        $this->resetAfterTest();
        $this->mark_installed('local_forum_ai');

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiprovider/datacurso:viewreports', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        set_plugin_enabled::execute('local_forum_ai', false);
    }

    /**
     * A component outside the suite list is rejected and nothing is written.
     *
     * S6: only suite plugins can be switched.
     */
    public function test_execute_rejects_components_outside_the_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        try {
            set_plugin_enabled::execute('mod_forum', false);
            $this->fail('A component outside the suite must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidsuiteplugin', $e->errorcode);
        }
        $this->assertFalse(get_config('aiprovider_datacurso', 'plugin_enabled_mod_forum'));
    }

    /**
     * A suite plugin that is not installed has no switch, so the service refuses it.
     *
     * S6: only installed plugins can be switched.
     */
    public function test_execute_rejects_plugins_that_are_not_installed(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $DB->delete_records('config_plugins', ['plugin' => 'local_coursegen']);

        try {
            set_plugin_enabled::execute('local_coursegen', false);
            $this->fail('A plugin that is not installed must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('pluginnotinstalled', $e->errorcode);
        }
        $this->assertTrue(plugin_settings::is_enabled('local_coursegen'));
    }

    /**
     * A site administrator switches a plugin for one tenant only; the site default and other tenants keep theirs.
     *
     * Workplace: disabling a plugin in tenant A does not disable it in tenant B.
     */
    public function test_execute_changes_only_the_tenant_asked_for(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->mark_installed('local_forum_ai');

        $result = set_plugin_enabled::execute('local_forum_ai', false, 5);
        $result = \core_external\external_api::clean_returnvalue(set_plugin_enabled::execute_returns(), $result);

        $this->assertFalse($result['enabled']);
        $this->assertSame(5, $result['tenantid']);
        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai', 5));
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai', 6));
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai', 0));
        $this->assertFalse(get_config('aiprovider_datacurso', 'plugin_enabled_local_forum_ai'));
    }

    /**
     * A user who manages the plugins of their own tenant cannot change another tenant.
     *
     * Workplace: a tenant administrator only manages their own tenant.
     */
    public function test_execute_refuses_another_tenant_to_a_tenant_manager(): void {
        $this->resetAfterTest();
        $this->mark_installed('local_forum_ai');

        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiprovider/datacurso:manageconfig', CAP_ALLOW, $roleid, \context_system::instance());
        role_assign($roleid, $user->id, \context_system::instance());
        $this->setUser($user);

        // Their own tenant (the single implicit tenant of a site without tenancy) is allowed.
        $result = set_plugin_enabled::execute('local_forum_ai', false);
        $this->assertFalse($result['enabled']);

        try {
            set_plugin_enabled::execute('local_forum_ai', false, 5);
            $this->fail('Another tenant must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertNull(\aiprovider_datacurso\local\tenant_config::get_stored(
            'aiprovider_datacurso',
            5,
            'plugin_enabled_local_forum_ai'
        ));
    }
}
