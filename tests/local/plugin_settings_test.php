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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/ai/provider/datacurso/tests/fixtures/fake_settings_extension.php');
require_once($CFG->dirroot . '/ai/provider/datacurso/tests/fixtures/unrelated_settings_extension.php');

/**
 * Tests for the per-plugin state kept by the provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\plugin_settings
 */
final class plugin_settings_test extends \advanced_testcase {
    /**
     * Fetch the config_log rows written for a provider setting, newest first.
     *
     * @param string $name Setting name.
     * @return \stdClass[]
     */
    private function config_log_rows(string $name): array {
        global $DB;
        return array_values($DB->get_records(
            'config_log',
            ['plugin' => 'aiprovider_datacurso', 'name' => $name],
            'id DESC'
        ));
    }

    /**
     * Without a saved value a plugin is enabled; otherwise the saved value decides.
     *
     * S2: is_enabled() defaults to true.
     */
    public function test_is_enabled_defaults_to_true_and_follows_the_saved_value(): void {
        $this->resetAfterTest();

        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));

        set_config('plugin_enabled_local_forum_ai', '0', 'aiprovider_datacurso');
        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai'));

        set_config('plugin_enabled_local_forum_ai', '1', 'aiprovider_datacurso');
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));

        // Each plugin has its own setting.
        set_config('plugin_enabled_local_dttutor', '0', 'aiprovider_datacurso');
        $this->assertFalse(plugin_settings::is_enabled('local_dttutor'));
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));
    }

    /**
     * Changing the state writes the setting and a config_log row; saving the same state adds no row.
     *
     * S2: every change is audited.
     */
    public function test_set_enabled_is_audited_only_on_change(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        plugin_settings::set_enabled('local_jedomi', false);

        $this->assertSame('0', get_config('aiprovider_datacurso', 'plugin_enabled_local_jedomi'));
        $this->assertFalse(plugin_settings::is_enabled('local_jedomi'));
        $rows = $this->config_log_rows('plugin_enabled_local_jedomi');
        $this->assertCount(1, $rows);
        $this->assertSame('0', $rows[0]->value);

        plugin_settings::set_enabled('local_jedomi', false);
        $this->assertCount(1, $this->config_log_rows('plugin_enabled_local_jedomi'));

        plugin_settings::set_enabled('local_jedomi', true);
        $rows = $this->config_log_rows('plugin_enabled_local_jedomi');
        $this->assertCount(2, $rows);
        $this->assertSame('1', $rows[0]->value);
        $this->assertSame('0', $rows[0]->oldvalue);
    }

    /**
     * Only suite plugins can be switched on or off.
     *
     * S2: unknown components are rejected.
     */
    public function test_set_enabled_rejects_unknown_components(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        plugin_settings::set_enabled('local_unknown', false);
    }

    /**
     * A plugin extension is used only when its class implements the provider interface.
     *
     * S5: the provider finds the extension by name without referencing the plugin.
     */
    public function test_get_extension_resolves_only_classes_implementing_the_interface(): void {
        $extension = plugin_settings::get_extension('local_fakesuite');
        $this->assertInstanceOf(plugin_settings_extension::class, $extension);
        $this->assertInstanceOf(\local_fakesuite\aiprovider\settings_extension::class, $extension);

        // A class with the right name that does not implement the interface is ignored.
        $this->assertNull(plugin_settings::get_extension('local_fakeother'));

        // A plugin without the class has no extension.
        $this->assertNull(plugin_settings::get_extension('local_forum_ai'));
    }

    /**
     * The upgrade copies the current flag of each plugin and leaves the others enabled.
     *
     * S4: migration of the per-plugin enabled flags.
     */
    public function test_migrate_legacy_flags_copies_each_plugin_value(): void {
        $this->resetAfterTest();

        set_config('enableforumai', '0', 'local_forum_ai');
        set_config('enableassignai', '1', 'local_assign_ai');
        set_config('enabled', '0', 'local_dttutor');
        set_config('enabled', '', 'local_datacurso_ratings');
        set_config('enableai', '0', 'local_socialcert');
        // Jedomi treats only '0' as disabled: an empty value keeps it enabled.
        set_config('enabled', '', 'local_jedomi');

        plugin_settings::migrate_legacy_flags();

        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai'));
        $this->assertTrue(plugin_settings::is_enabled('local_assign_ai'));
        $this->assertFalse(plugin_settings::is_enabled('local_dttutor'));
        $this->assertFalse(plugin_settings::is_enabled('local_datacurso_ratings'));
        $this->assertFalse(plugin_settings::is_enabled('local_socialcert'));
        $this->assertTrue(plugin_settings::is_enabled('local_jedomi'));

        // Plugins without a flag stay enabled.
        $this->assertTrue(plugin_settings::is_enabled('local_coursegen'));
        $this->assertTrue(plugin_settings::is_enabled('local_coursedynamicrules'));
        $this->assertTrue(plugin_settings::is_enabled('report_lifestory'));

        // The old flags are left for each plugin to remove in its own upgrade.
        $this->assertSame('0', get_config('local_forum_ai', 'enableforumai'));
    }

    /**
     * A disabled Jedomi stays disabled, and a missing flag leaves the plugin enabled.
     *
     * S4: Jedomi '0'-only semantics and missing values.
     */
    public function test_migrate_legacy_flags_keeps_jedomi_disabled_and_ignores_missing_flags(): void {
        $this->resetAfterTest();

        set_config('enabled', '0', 'local_jedomi');
        unset_config('enableforumai', 'local_forum_ai');
        unset_config('enabled', 'local_dttutor');

        plugin_settings::migrate_legacy_flags();

        $this->assertFalse(plugin_settings::is_enabled('local_jedomi'));
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));
        $this->assertTrue(plugin_settings::is_enabled('local_dttutor'));
        $this->assertFalse(get_config('aiprovider_datacurso', 'plugin_enabled_local_forum_ai'));
    }

    /**
     * The migration never overwrites a state already saved in the provider.
     *
     * S4: running the migration twice is safe.
     */
    public function test_migrate_legacy_flags_keeps_a_state_already_saved(): void {
        $this->resetAfterTest();

        set_config('plugin_enabled_local_forum_ai', '1', 'aiprovider_datacurso');
        set_config('enableforumai', '0', 'local_forum_ai');

        plugin_settings::migrate_legacy_flags();

        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));
    }
    /**
     * A plugin has settings beyond "Enabled" only when it ships an extension.
     *
     * S5: the plugins tab links to the settings page only when there is something to configure.
     */
    public function test_has_settings_only_for_plugins_with_an_extension(): void {
        $this->assertTrue(plugin_settings::has_settings('local_fakesuite'));
        $this->assertFalse(plugin_settings::has_settings('local_fakeother'));
        $this->assertFalse(plugin_settings::has_settings('local_forum_ai'));
    }

    /**
     * Run require_enabled() and return the exception it throws.
     *
     * @param string $component Frankenstyle component name.
     * @return \moodle_exception
     */
    private function require_enabled_exception(string $component): \moodle_exception {
        try {
            plugin_settings::require_enabled($component);
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail("require_enabled('{$component}') did not throw");
    }

    /**
     * An enabled plugin, or one without a saved state, passes the guard.
     */
    public function test_require_enabled_passes_for_enabled_and_unsaved_plugins(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        // No saved state: enabled.
        plugin_settings::require_enabled('local_forum_ai');

        set_config('plugin_enabled_local_forum_ai', '1', 'aiprovider_datacurso');
        plugin_settings::require_enabled('local_forum_ai');

        // No exception was thrown.
        $this->assertTrue(plugin_settings::is_enabled('local_forum_ai'));
    }

    /**
     * A disabled plugin tells a user without site configuration to contact the administrator.
     */
    public function test_require_enabled_tells_a_user_to_contact_the_administrator(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('plugin_enabled_local_datacurso_ratings', '0', 'aiprovider_datacurso');

        $e = $this->require_enabled_exception('local_datacurso_ratings');

        $name = get_string('pluginname_datacurso_ratings', 'aiprovider_datacurso');
        $this->assertSame('plugindisabled', $e->errorcode);
        $this->assertSame('aiprovider_datacurso', $e->module);
        $this->assertSame(get_string('plugindisabled', 'aiprovider_datacurso', $name), $e->getMessage());
        $this->assertStringContainsString($name, $e->getMessage());
        $this->assertStringNotContainsString('report_sections.php', $e->getMessage());
    }

    /**
     * A disabled plugin shows a site administrator a link to the plugins list, also as the Continue link.
     */
    public function test_require_enabled_links_an_administrator_to_the_plugins_list(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('plugin_enabled_local_dttutor', '0', 'aiprovider_datacurso');

        $e = $this->require_enabled_exception('local_dttutor');

        $url = new \moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'pluginslist']);
        $this->assertSame('plugindisabled_admin', $e->errorcode);
        $this->assertStringContainsString(get_string('pluginname_dttutor', 'aiprovider_datacurso'), $e->getMessage());
        $this->assertStringContainsString('href="' . $url->out() . '"', $e->getMessage());
        $this->assertSame($url->out(false), (new \moodle_url($e->link))->out(false));
    }

    /**
     * A component outside the suite is named by its own pluginname string.
     */
    public function test_require_enabled_names_other_components_by_their_pluginname(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());
        set_config('plugin_enabled_aiprovider_datacurso', '0', 'aiprovider_datacurso');

        $e = $this->require_enabled_exception('aiprovider_datacurso');

        $this->assertStringContainsString(get_string('pluginname', 'aiprovider_datacurso'), $e->getMessage());
    }
}
