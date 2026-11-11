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

namespace aiprovider_datacurso;

use aiprovider_datacurso\local\plugin_settings;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/upgradelib.php');
require_once($CFG->dirroot . '/ai/provider/datacurso/db/upgrade.php');

/**
 * Tests for the provider upgrade steps.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers ::xmldb_aiprovider_datacurso_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * The 2026102900 step copies the current enabled flag of each plugin into the provider.
     *
     * S4: no site changes state when it upgrades.
     */
    public function test_upgrade_copies_the_enabled_flag_of_each_plugin(): void {
        $this->resetAfterTest();

        set_config('enableforumai', '0', 'local_forum_ai');
        set_config('enabled', '0', 'local_jedomi');
        set_config('enableai', '1', 'local_socialcert');
        set_config('version', 2026102800, 'aiprovider_datacurso');

        $this->assertTrue(xmldb_aiprovider_datacurso_upgrade(2026102800));
        // xmldb warns about the empty default of licence, declared the same in db/install.xml.
        $this->resetDebugging();
        // The savepoints mark an upgrade as running; the next test would otherwise see it.
        unset_config('upgraderunning');

        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai'));
        $this->assertFalse(plugin_settings::is_enabled('local_jedomi'));
        $this->assertTrue(plugin_settings::is_enabled('local_socialcert'));
        $this->assertTrue(plugin_settings::is_enabled('local_coursegen'));
        $this->assertSame('2026111002', get_config('aiprovider_datacurso', 'version'));
    }

    /**
     * A site coming from the provider of MOODLE_405_STABLE gets the tables and columns of Workplace.
     *
     * That branch has neither the settings per tenant nor the tenant and licence of each consumption
     * record, and its version (2026110400) is above the Workplace steps that create them.
     */
    public function test_a_site_from_the_moodle_branch_gets_the_workplace_schema(): void {
        global $DB;
        $this->resetAfterTest();
        $dbman = $DB->get_manager();

        // The schema of MOODLE_405_STABLE 1.7.0: no tenant settings, no tenant nor licence per record.
        $consumption = new \xmldb_table('aiprovider_datacurso_consumption');
        foreach (['tenant_id', 'licence'] as $name) {
            $index = new \xmldb_index($name, XMLDB_INDEX_NOTUNIQUE, [$name]);
            if ($dbman->index_exists($consumption, $index)) {
                $dbman->drop_index($consumption, $index);
            }
            $dbman->drop_field($consumption, new \xmldb_field($name));
        }
        $dbman->drop_table(new \xmldb_table('aiprovider_datacurso_tenant_config'));
        $DB->insert_record('aiprovider_datacurso_consumption', (object)[
            'externalid' => 7, 'userid' => 0, 'service' => 'local_dttutor', 'action' => 'chat',
            'credits' => 1, 'balance' => 9, 'timecreated' => time(),
        ]);
        set_config('version', 2026110400, 'aiprovider_datacurso');

        $this->assertTrue(xmldb_aiprovider_datacurso_upgrade(2026110400));
        // xmldb warns about the empty default of licence, declared the same in db/install.xml.
        $this->resetDebugging();
        // The savepoints mark an upgrade as running; the next test would otherwise see it.
        unset_config('upgraderunning');

        $this->assertTrue($dbman->field_exists($consumption, 'tenant_id'));
        $this->assertTrue($dbman->field_exists($consumption, 'licence'));
        $this->assertTrue($dbman->index_exists($consumption, new \xmldb_index('tenant_id', XMLDB_INDEX_NOTUNIQUE, ['tenant_id'])));
        $this->assertTrue($dbman->table_exists('aiprovider_datacurso_tenant_config'));
        // The rows mirrored without a tenant cannot be attributed, so they are pulled again.
        $this->assertSame(0, $DB->count_records('aiprovider_datacurso_consumption'));
        $this->assertSame(0, $DB->count_records('aiprovider_datacurso_consumption', ['tenant_id' => 0, 'licence' => '']));
        $this->assertSame('2026111002', get_config('aiprovider_datacurso', 'version'));
    }

    /**
     * A Workplace site that already has the schema keeps it and its consumption records.
     */
    public function test_a_workplace_site_keeps_its_schema_and_records(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('aiprovider_datacurso_consumption', (object)[
            'externalid' => 8, 'userid' => 0, 'tenant_id' => 3, 'licence' => sha1('DC-KEY'),
            'service' => 'local_dttutor', 'action' => 'chat', 'credits' => 1, 'balance' => 9, 'timecreated' => time(),
        ]);
        set_config('version', 2026110901, 'aiprovider_datacurso');

        $this->assertTrue(xmldb_aiprovider_datacurso_upgrade(2026110901));
        // xmldb warns about the empty default of licence, declared the same in db/install.xml.
        $this->resetDebugging();
        // The savepoints mark an upgrade as running; the next test would otherwise see it.
        unset_config('upgraderunning');

        $this->assertSame(1, $DB->count_records('aiprovider_datacurso_consumption', ['externalid' => 8, 'tenant_id' => 3]));
        $this->assertSame('2026111002', get_config('aiprovider_datacurso', 'version'));
    }
}
