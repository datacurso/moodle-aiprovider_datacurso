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
 * Tests for the upgrade step that moves the suite plugins' "Enabled" flags into the provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_aiprovider_datacurso_upgrade')]
final class upgrade_plugin_settings_test extends \advanced_testcase {
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

        $this->assertFalse(plugin_settings::is_enabled('local_forum_ai'));
        $this->assertFalse(plugin_settings::is_enabled('local_jedomi'));
        $this->assertTrue(plugin_settings::is_enabled('local_socialcert'));
        $this->assertTrue(plugin_settings::is_enabled('local_coursegen'));
        // Later steps also run, so the savepoint of this step is reached or passed.
        $this->assertGreaterThanOrEqual(2026102900, (int) get_config('aiprovider_datacurso', 'version'));
    }
}
