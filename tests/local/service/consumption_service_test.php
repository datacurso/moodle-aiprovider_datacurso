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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for consumption_service::get_summary().
 *
 * Reads directly from the local mirror table {aiprovider_datacurso_consumption} (kept up to
 * date by {@see \aiprovider_datacurso\local\sync\consumption_sync}) and aggregates credit
 * totals for the report charts.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(consumption_service::class)]
final class consumption_service_test extends \advanced_testcase {
    /** @var string Local mirror table. */
    private const TABLE = 'aiprovider_datacurso_consumption';

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert a consumption row directly into the local mirror table.
     *
     * @param string $service
     * @param string $action
     * @param float $credits
     * @param string $datetime
     * @param int $userid
     */
    private function insert_consumption(
        string $service,
        string $action,
        float $credits,
        string $datetime,
        int $userid = 0
    ): void {
        global $DB;

        static $externalid = 0;
        $externalid++;

        $record = new \stdClass();
        $record->externalid = $externalid;
        $record->userid = $userid;
        $record->service = $service;
        $record->action = $action;
        $record->credits = $credits;
        $record->balance = 1000 - $credits;
        $record->timecreated = strtotime($datetime);
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Seed a small, mixed dataset spanning two services/actions and two months.
     */
    private function seed_dataset(): void {
        // Row A: aiprovider_datacurso / chat-completions, January, userid 7.
        $this->insert_consumption(
            'aiprovider_datacurso',
            '/provider/chat/completions',
            10,
            '2026-01-15 08:00:00',
            7
        );
        // Row B: local_coursegen / course execute, February.
        $this->insert_consumption(
            'local_coursegen',
            '/course/execute',
            20,
            '2026-02-20 09:00:00'
        );
        // Row C: aiprovider_datacurso / chat-completions, January (different day).
        $this->insert_consumption(
            'aiprovider_datacurso',
            '/provider/chat/completions',
            5,
            '2026-01-20 10:00:00'
        );
    }

    public function test_get_summary_groups_by_month(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('month');

        $this->assertSame('success', $result['status']);
        $this->assertEqualsWithDelta(35.0, $result['total'], 0.001);
        $this->assertSame(['2026-01', '2026-02'], array_column($result['summary'], 'label'));
        $this->assertEqualsWithDelta(15.0, $result['summary'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(20.0, $result['summary'][1]['total'], 0.001);
    }

    public function test_get_summary_groups_by_day(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('day');

        $this->assertSame(['2026-01-15', '2026-01-20', '2026-02-20'], array_column($result['summary'], 'label'));
    }

    public function test_get_summary_groups_by_action_descending_by_total(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('action');

        // The local_coursegen /course/execute action (20 credits) outweighs the chat action
        // (15 credits), and categorical buckets must be sorted descending by total.
        $totals = array_column($result['summary'], 'total');
        $this->assertSame($totals, array_values((function () use ($totals) {
            $sorted = $totals;
            rsort($sorted);
            return $sorted;
        })()));
        $this->assertEqualsWithDelta(20.0, $result['summary'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(15.0, $result['summary'][1]['total'], 0.001);
    }

    public function test_get_summary_groups_by_service_descending_by_total(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('service');

        $this->assertEqualsWithDelta(20.0, $result['summary'][0]['total'], 0.001);
        $this->assertEqualsWithDelta(15.0, $result['summary'][1]['total'], 0.001);
    }

    public function test_get_summary_honors_service_filter(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('month', 'local_coursegen');

        $this->assertEqualsWithDelta(20.0, $result['total'], 0.001);
    }

    public function test_get_summary_honors_action_filter(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('month', null, '/provider/chat/completions');

        $this->assertEqualsWithDelta(15.0, $result['total'], 0.001);
    }

    public function test_get_summary_honors_userid_filter(): void {
        $this->seed_dataset();

        $result = consumption_service::get_summary('month', null, null, 7);

        $this->assertEqualsWithDelta(10.0, $result['total'], 0.001);
    }

    public function test_get_summary_honors_fromdate_and_todate_filters(): void {
        $this->seed_dataset();

        // Excludes row A (Jan 15) and row B (Feb 20); keeps only row C (Jan 20).
        $result = consumption_service::get_summary('month', null, null, null, '2026-01-18', '2026-01-31');

        $this->assertEqualsWithDelta(5.0, $result['total'], 0.001);
    }

    public function test_invalid_groupby_falls_back_to_month(): void {
        $this->seed_dataset();

        $bogus = consumption_service::get_summary('not-a-real-dimension');
        $month = consumption_service::get_summary('month');

        $this->assertSame($month['summary'], $bogus['summary']);
        $this->assertEqualsWithDelta($month['total'], $bogus['total'], 0.001);
    }

    /**
     * Action ids resolve to their catalogued human name; an unknown action id passes through
     * verbatim instead of breaking the aggregation.
     *
     * Ported from 4.5's tests/local/service/consumption_service_test.php::test_action_ids_resolve_to_human_names.
     */
    public function test_action_ids_resolve_to_human_names(): void {
        $this->seed_dataset();
        $this->insert_consumption('ghost_service', 'ghost_action', 3, '2026-02-11 08:00:00');

        $result = consumption_service::get_summary('action');

        $actionmap = [];
        foreach (provider::get_actions() as $item) {
            $actionmap[$item['id']] = $item['name'];
        }

        $labels = array_column($result['summary'], 'label');

        $this->assertContains($actionmap['/provider/chat/completions'], $labels);
        $this->assertContains($actionmap['/course/execute'], $labels);
        // Unknown id is shown verbatim without breaking the aggregation.
        $this->assertContains('ghost_action', $labels);
    }

    /**
     * Service ids resolve to their plugin name; an unknown service id passes through verbatim.
     *
     * Ported from 4.5's tests/local/service/consumption_service_test.php::test_service_ids_resolve_to_plugin_names.
     */
    public function test_service_ids_resolve_to_plugin_names(): void {
        $this->seed_dataset();
        $this->insert_consumption('ghost_service', 'ghost_action', 3, '2026-02-11 08:00:00');

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
}
