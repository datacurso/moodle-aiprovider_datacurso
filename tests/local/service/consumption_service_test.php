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

namespace aiprovider_datacurso\local\service;

use aiprovider_datacurso\provider;

/**
 * Tests for the consumption aggregation service that feeds the report charts.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\service\consumption_service
 */
final class consumption_service_test extends \advanced_testcase {
    /** @var int A Moodle user id owning the fixture rows. */
    private int $userid;

    /**
     * Seed the local consumption mirror table with a small deterministic dataset.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->userid = $this->getDataGenerator()->create_user()->id;

        // Mid-day timestamps so the server timezone cannot shift the calendar day.
        $rows = [
            // externalid, service, action, credits, month, day.
            [1, 'local_coursegen', '/provider/chat/completions', 10, 1, 15],
            [2, 'local_coursegen', '/provider/chat/completions', 5, 1, 20],
            [3, 'aiprovider_datacurso', '/provider/images/generations', 30, 2, 10],
            [4, 'ghost_service', 'ghost_action', 3, 2, 11],
        ];

        $balance = 1000.0;
        foreach ($rows as [$externalid, $service, $action, $credits, $mon, $day]) {
            $balance -= $credits;
            $DB->insert_record('aiprovider_datacurso_consumption', (object) [
                'externalid' => $externalid,
                'userid' => $this->userid,
                'service' => $service,
                'action' => $action,
                'credits' => $credits,
                'balance' => $balance,
                'timecreated' => mktime(12, 0, 0, $mon, $day, 2026),
            ]);
        }
    }

    /**
     * Credits aggregate per month and are ordered chronologically; the total is the filtered sum.
     *
     * MDL-UNIT-007: consumption aggregation for the charts (by month).
     */
    public function test_summary_by_month_is_chronological(): void {
        $result = consumption_service::get_summary('month');

        $this->assertSame('success', $result['status']);
        $this->assertEqualsWithDelta(48.0, $result['total'], 0.001);

        $labels = array_column($result['summary'], 'label');
        $this->assertSame(['2026-01', '2026-02'], $labels);
        $this->assertEqualsWithDelta(15.0, $result['summary'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(33.0, $result['summary'][1]['total'], 0.001);
    }

    /**
     * Credits aggregate per day, ordered chronologically.
     *
     * MDL-UNIT-007: consumption aggregation for the charts (by day).
     */
    public function test_summary_by_day_is_chronological(): void {
        $result = consumption_service::get_summary('day');

        $labels = array_column($result['summary'], 'label');
        $this->assertSame(['2026-01-15', '2026-01-20', '2026-02-10', '2026-02-11'], $labels);
        $this->assertEqualsWithDelta(48.0, $result['total'], 0.001);
    }

    /**
     * Categorical dimensions are ordered by total descending.
     *
     * MDL-UNIT-007: categorical ordering (by service).
     */
    public function test_summary_by_service_is_ordered_by_total_desc(): void {
        $result = consumption_service::get_summary('service');

        $totals = array_column($result['summary'], 'total');
        $sorted = $totals;
        rsort($sorted);
        $this->assertSame($sorted, $totals);
        // Largest bucket first: aiprovider_datacurso with 30 credits.
        $this->assertEqualsWithDelta(30.0, $result['summary'][0]['total'], 0.001);
    }

    /**
     * Action ids resolve to human names; a service filter narrows the total.
     *
     * MDL-UNIT-008: identifier-to-name translation for actions.
     */
    public function test_action_ids_resolve_to_human_names(): void {
        $result = consumption_service::get_summary('action');

        $actionmap = [];
        foreach (provider::get_actions() as $item) {
            $actionmap[$item['id']] = $item['name'];
        }

        $labels = array_column($result['summary'], 'label');

        // Known ids appear under their readable name.
        $this->assertContains($actionmap['/provider/chat/completions'], $labels);
        $this->assertContains($actionmap['/provider/images/generations'], $labels);
        // Unknown id is shown verbatim without breaking.
        $this->assertContains('ghost_action', $labels);
    }

    /**
     * Service ids resolve to their plugin names; unknown ids appear verbatim.
     *
     * MDL-UNIT-008: identifier-to-name translation for services.
     */
    public function test_service_ids_resolve_to_plugin_names(): void {
        $result = consumption_service::get_summary('service');

        $servicemap = [];
        foreach (provider::get_services() as $item) {
            $servicemap[$item['id']] = $item['name'];
        }

        $labels = array_column($result['summary'], 'label');
        $this->assertContains($servicemap['local_coursegen'], $labels);
        $this->assertContains($servicemap['aiprovider_datacurso'], $labels);
        $this->assertContains('ghost_service', $labels);
    }

    /**
     * A service filter restricts the aggregated total to the matching rows.
     *
     * MDL-UNIT-007: the total equals the sum of the filtered set.
     */
    public function test_service_filter_narrows_total(): void {
        $result = consumption_service::get_summary('month', 'local_coursegen');

        $this->assertEqualsWithDelta(15.0, $result['total'], 0.001);
    }
}
