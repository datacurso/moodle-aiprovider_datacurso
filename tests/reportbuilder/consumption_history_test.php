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

namespace aiprovider_datacurso\reportbuilder;

use aiprovider_datacurso\reportbuilder\local\systemreports\consumption_history;
use core_reportbuilder\system_report_factory;

/**
 * Tests for the consumption history system report.
 *
 * Covers the data-layer contract: the report builds over the local mirror table and exposes the
 * expected columns. Visual filtering, sorting and pagination are exercised by the Behat feature
 * MDL-E2E-002; user-name and id-to-name resolution is unit-tested in the consumption service tests.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\reportbuilder\local\systemreports\consumption_history
 */
final class consumption_history_test extends \advanced_testcase {
    /**
     * The report instantiates over the local table and exposes the expected columns.
     *
     * MDL-INT-004: rendering the history with the report builder table.
     */
    public function test_report_builds_with_expected_columns(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('aiprovider_datacurso_consumption', (object) [
            'externalid' => 1,
            'userid' => $user->id,
            'service' => 'local_coursegen',
            'action' => '/course/execute',
            'credits' => 10,
            'balance' => 990,
            'timecreated' => time(),
        ]);

        $report = system_report_factory::create(
            consumption_history::class,
            \context_system::instance()
        );

        $this->assertInstanceOf(consumption_history::class, $report);

        $titles = array_map(
            static fn($column): string => $column->get_title(),
            $report->get_active_columns()
        );

        // The seven expected columns: id, user, action, service, credits, balance, date.
        $this->assertCount(7, $titles);
        $this->assertContains(get_string('id', 'aiprovider_datacurso'), $titles);
        $this->assertContains(get_string('action', 'aiprovider_datacurso'), $titles);
        $this->assertContains(get_string('service', 'aiprovider_datacurso'), $titles);
        $this->assertContains(get_string('tokensused', 'aiprovider_datacurso'), $titles);
        $this->assertContains(get_string('remainingtokens', 'aiprovider_datacurso'), $titles);
    }
}
