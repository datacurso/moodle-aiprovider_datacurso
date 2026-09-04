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

namespace aiprovider_datacurso\local\sync;

use aiprovider_datacurso\httpclient\test_consumption_api_client;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/test_consumption_api_client.php');

/**
 * Tests for consumption_sync::sync().
 *
 * The Report Builder consumption report reads from the local mirror table
 * {aiprovider_datacurso_consumption}; this service keeps it in sync with the external
 * Datacurso API, walking pages newest-first until it reaches an already-known record.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(consumption_sync::class)]
final class consumption_sync_test extends \advanced_testcase {
    /** @var string Local mirror table. */
    private const TABLE = 'aiprovider_datacurso_consumption';

    /**
     * Seed an enabled provider instance with a license key.
     *
     * datacurso_api::__construct() scans core_ai\manager::get_provider_instances() and
     * throws moodle_exception('instance_disabled')/('licensekey_missing') when none is
     * enabled with a license key, so every test needs one present in the DB even though
     * the client itself is faked.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $manager = new \core_ai\manager($DB);
        $manager->create_provider_instance(
            classname: \aiprovider_datacurso\provider::class,
            name: 'test',
            enabled: true,
            config: ['licensekey' => 'test-key'],
        );
    }

    /**
     * Build a single raw consumption item as the remote API returns it.
     *
     * @param int $id
     * @param string $datekey Either 'created_at' or 'fecha'.
     * @param string $date
     * @return array
     */
    private function make_consumption(int $id, string $datekey = 'created_at', string $date = '2026-08-27 00:00:00'): array {
        return [
            'id_consumo' => $id,
            'userid' => 0,
            'accion' => '/provider/chat/completions',
            'id_servicio' => 'aiprovider_datacurso',
            'cantidad_tokens' => 10,
            'saldo_restante' => 990,
            $datekey => $date,
        ];
    }

    /**
     * Wrap raw consumption items into a successful historial-consumos page response.
     *
     * @param array $items
     * @param int $totalpages
     * @return array
     */
    private function make_page(array $items, int $totalpages = 1): array {
        return [
            'status' => 'success',
            'usuarios' => [
                ['consumos' => $items],
            ],
            'paginacion' => ['total_paginas' => $totalpages],
        ];
    }

    public function test_first_run_backfills_all_pages(): void {
        global $DB;

        $client = new test_consumption_api_client();
        $client->responsesbypage[1] = $this->make_page([
            $this->make_consumption(20),
            $this->make_consumption(19),
        ], 2);
        $client->responsesbypage[2] = $this->make_page([
            $this->make_consumption(18),
        ], 2);

        consumption_sync::sync($client);

        $records = $DB->get_records(self::TABLE);
        $this->assertCount(3, $records);
        $externalids = array_map(static fn($r) => (int) $r->externalid, $records);
        sort($externalids);
        $this->assertSame([18, 19, 20], $externalids);
        $this->assertCount(2, $client->calls);
    }

    public function test_second_run_inserts_nothing_once_watermarked(): void {
        global $DB;

        $client1 = new test_consumption_api_client();
        $client1->responsesbypage[1] = $this->make_page([
            $this->make_consumption(20),
            $this->make_consumption(19),
        ], 1);
        consumption_sync::sync($client1);
        $this->assertCount(2, $DB->get_records(self::TABLE));

        // Second run: the API still serves the same top page, but every item is <= the
        // watermark (MAX(externalid) = 20), so nothing new should be inserted.
        $client2 = new test_consumption_api_client();
        $client2->responsesbypage[1] = $this->make_page([
            $this->make_consumption(20),
            $this->make_consumption(19),
        ], 1);
        consumption_sync::sync($client2);

        $this->assertCount(2, $DB->get_records(self::TABLE));
    }

    public function test_walk_stops_early_when_page_contains_known_externalid(): void {
        global $DB;

        // Seed the mirror directly so the watermark (MAX(externalid)) is 19.
        $existing = new \stdClass();
        $existing->externalid = 19;
        $existing->userid = 0;
        $existing->service = 'aiprovider_datacurso';
        $existing->action = '/provider/chat/completions';
        $existing->credits = 10;
        $existing->balance = 1000;
        $existing->timecreated = 1000;
        $DB->insert_record(self::TABLE, $existing);

        $client = new test_consumption_api_client();
        // Page 1: one genuinely new record (21) plus the already-known one (19).
        $client->responsesbypage[1] = $this->make_page([
            $this->make_consumption(21),
            $this->make_consumption(19),
        ], 3);
        // Page 2 would add more, but the walk must never reach it.
        $client->responsesbypage[2] = $this->make_page([
            $this->make_consumption(18),
        ], 3);

        consumption_sync::sync($client);

        $records = $DB->get_records(self::TABLE);
        $this->assertCount(2, $records);
        $externalids = array_map(static fn($r) => (int) $r->externalid, $records);
        sort($externalids);
        $this->assertSame([19, 21], $externalids);
        // The loop must have stopped after page 1 and never requested page 2.
        $this->assertCount(1, $client->calls);
    }

    public function test_non_success_page_breaks_loop_without_throwing_and_keeps_prior_inserts(): void {
        global $DB;

        $existing = new \stdClass();
        $existing->externalid = 5;
        $existing->userid = 0;
        $existing->service = 'aiprovider_datacurso';
        $existing->action = '/provider/chat/completions';
        $existing->credits = 10;
        $existing->balance = 1000;
        $existing->timecreated = 1000;
        $DB->insert_record(self::TABLE, $existing);

        // No responsesbypage entries set -> the fixture returns ['status' => 'error'].
        $client = new test_consumption_api_client();

        consumption_sync::sync($client);

        $records = $DB->get_records(self::TABLE);
        $this->assertCount(1, $records);
        $this->assertSame(5, (int) reset($records)->externalid);
    }

    public function test_rows_map_fields_correctly_including_strtotime_on_alternate_date_key(): void {
        global $DB;

        $client = new test_consumption_api_client();
        $client->responsesbypage[1] = $this->make_page([
            array_merge($this->make_consumption(30, 'created_at', '2026-08-27 10:00:00'), [
                'userid' => 42,
                'id_servicio' => 'local_coursegen',
                'accion' => 'course_image',
                'cantidad_tokens' => 2000,
                'saldo_restante' => 5000,
            ]),
            $this->make_consumption(31, 'fecha', '2026-08-28 11:30:00'),
        ], 1);

        consumption_sync::sync($client);

        $records = $DB->get_records(self::TABLE, [], 'externalid ASC');
        $this->assertCount(2, $records);
        [$first, $second] = array_values($records);

        $this->assertSame(30, (int) $first->externalid);
        $this->assertSame(42, (int) $first->userid);
        $this->assertSame('local_coursegen', $first->service);
        $this->assertSame('course_image', $first->action);
        $this->assertEqualsWithDelta(2000.0, (float) $first->credits, 0.001);
        $this->assertEqualsWithDelta(5000.0, (float) $first->balance, 0.001);
        $this->assertSame(strtotime('2026-08-27 10:00:00'), (int) $first->timecreated);

        $this->assertSame(31, (int) $second->externalid);
        $this->assertSame(strtotime('2026-08-28 11:30:00'), (int) $second->timecreated);
    }

    /**
     * The unique index on `externalid` makes it impossible to store two rows for the same
     * external consumption record, which is what keeps repeated syncs idempotent even if the
     * service-layer watermark check were ever bypassed.
     *
     * Ported from 4.5's tests/consumption_sync_test.php::test_duplicate_external_id_is_rejected.
     */
    public function test_duplicate_external_id_is_rejected_by_the_unique_index(): void {
        global $DB;

        $record = new \stdClass();
        $record->externalid = 555;
        $record->userid = 0;
        $record->service = 'aiprovider_datacurso';
        $record->action = '/provider/chat/completions';
        $record->credits = 10;
        $record->balance = 990;
        $record->timecreated = 1000;
        $DB->insert_record(self::TABLE, $record);

        // A second row with the same external id violates the unique index.
        $this->expectException(\dml_exception::class);
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * The sync requests the newest-first page of the full 500-row page size, sorted by
     * consumption id descending, so the watermark walk can stop as soon as it finds a known
     * record without ever requesting more than it needs.
     *
     * Ported from 4.5's tests/consumption_sync_test.php::test_response_payload_maps_to_local_record
     * (request-shape assertions only; field-mapping is already covered above).
     */
    public function test_requests_the_historial_consumos_endpoint_with_the_expected_params(): void {
        $client = new test_consumption_api_client();
        $client->responsesbypage[1] = $this->make_page([$this->make_consumption(1)]);

        consumption_sync::sync($client);

        $this->assertCount(1, $client->calls);
        $this->assertSame('/tokens/historial-consumos', $client->calls[0]['endpoint']);
        $this->assertSame('id_consumo', $client->calls[0]['params']['shor']);
        $this->assertSame('desc', $client->calls[0]['params']['shor_dir']);
        $this->assertSame(500, $client->calls[0]['params']['limit']);
    }

    /**
     * An empty or unparseable `created_at` becomes a timestamp of zero instead of raising an
     * error, while a valid date is still parsed correctly.
     *
     * Ported from 4.5's tests/consumption_sync_test.php::test_date_parsing_of_external_values.
     */
    public function test_date_parsing_handles_empty_and_garbage_values_without_error(): void {
        global $DB;

        $client = new test_consumption_api_client();
        $client->responsesbypage[1] = $this->make_page([
            $this->make_consumption(3, 'created_at', '2026-03-10 14:45:00'),
            $this->make_consumption(2, 'created_at', ''),
            $this->make_consumption(1, 'created_at', 'not a date at all'),
        ]);

        consumption_sync::sync($client);

        $bytimestamp = [];
        foreach ($DB->get_records(self::TABLE) as $row) {
            $bytimestamp[(int) $row->externalid] = (int) $row->timecreated;
        }

        $this->assertSame(strtotime('2026-03-10 14:45:00'), $bytimestamp[3]);
        $this->assertSame(0, $bytimestamp[2]);
        $this->assertSame(0, $bytimestamp[1]);
    }
}
