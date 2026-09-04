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

use aiprovider_datacurso\httpclient\fake_datacurso_api;
use aiprovider_datacurso\local\sync\testable_consumption_sync;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_datacurso_api.php');
require_once(__DIR__ . '/fixtures/testable_consumption_sync.php');

/**
 * Tests for the local consumption mirror and its synchronisation.
 *
 * The synchronisation exposes a substitution point for its API client, so the watermark loop,
 * the payload mapping and the date parsing are exercised through the public sync() entry point
 * with a client double, without touching the network.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\sync\consumption_sync
 */
final class consumption_sync_test extends \advanced_testcase {
    /**
     * Reset the injected client after every test.
     */
    protected function tearDown(): void {
        testable_consumption_sync::$client = null;
        parent::tearDown();
    }

    /**
     * Build a consumption item as returned by the external service.
     *
     * @param int $externalid External consumption id.
     * @param array $overrides Field overrides.
     * @return array
     */
    private function make_item(int $externalid, array $overrides = []): array {
        return $overrides + [
            'id_consumo' => $externalid,
            'userid' => 7,
            'id_servicio' => 'local_coursegen',
            'accion' => '/course/execute',
            'cantidad_tokens' => 12.5,
            'saldo_restante' => 987.5,
            'created_at' => '2026-01-15 10:30:00',
        ];
    }

    /**
     * Wrap consumption items in the response envelope used by the service.
     *
     * @param array $items Consumption items.
     * @param int $totalpages Total pages reported by the service.
     * @return array
     */
    private function make_response(array $items, int $totalpages = 1): array {
        return [
            'status' => 'success',
            'usuarios' => [['consumos' => $items]],
            'paginacion' => ['total_paginas' => $totalpages],
        ];
    }

    /**
     * Run a sync with the given queued responses.
     *
     * @param array $responses Responses handed out one per request.
     * @return fake_datacurso_api
     */
    private function sync_with(array $responses): fake_datacurso_api {
        $client = new fake_datacurso_api();
        $client->responses = $responses;
        testable_consumption_sync::$client = $client;
        testable_consumption_sync::sync();
        return $client;
    }

    /**
     * Build a full consumption record for the mirror table.
     *
     * @param int $externalid
     * @return \stdClass
     */
    private function make_record(int $externalid): \stdClass {
        return (object) [
            'externalid' => $externalid,
            'userid' => 0,
            'service' => 'local_coursegen',
            'action' => '/course/execute',
            'credits' => 12.5,
            'balance' => 987.5,
            'timecreated' => 1735732800,
        ];
    }

    /**
     * The unique external id index makes re-inserting the same external record impossible,
     * which is what keeps the synchronisation idempotent.
     *
     * MDL-INT-003: idempotent insertion into the local consumption mirror.
     * MDL-UNIT-006: idempotency aspect of the sync watermark (same guarantee).
     */
    public function test_duplicate_external_id_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record('aiprovider_datacurso_consumption', $this->make_record(555));

        // A second row with the same external id violates the unique index.
        $this->expectException(\dml_exception::class);
        $DB->insert_record('aiprovider_datacurso_consumption', $this->make_record(555));
    }

    /**
     * A first sync inserts new rows with all mirrored fields present.
     *
     * MDL-INT-003: new records are stored with user, service, action, credits, balance and date.
     */
    public function test_records_are_stored_with_all_fields(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record('aiprovider_datacurso_consumption', $this->make_record(777));

        $stored = $DB->get_record('aiprovider_datacurso_consumption', ['externalid' => 777]);
        $this->assertNotFalse($stored);
        $this->assertSame('local_coursegen', $stored->service);
        $this->assertSame('/course/execute', $stored->action);
        $this->assertEqualsWithDelta(12.5, (float) $stored->credits, 0.001);
        $this->assertEqualsWithDelta(987.5, (float) $stored->balance, 0.001);
        $this->assertSame(1735732800, (int) $stored->timecreated);
    }

    /**
     * Only records newer than the highest stored external id are imported, and the walk stops
     * as soon as an already known record is reached.
     *
     * MDL-UNIT-006: incremental watermark of the sync.
     */
    public function test_watermark_imports_only_newer_records(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record('aiprovider_datacurso_consumption', $this->make_record(100));

        $this->sync_with([$this->make_response([
            $this->make_item(102),
            $this->make_item(101),
            $this->make_item(100),
            $this->make_item(99),
        ])]);

        $stored = $DB->get_fieldset_select('aiprovider_datacurso_consumption', 'externalid', '', []);
        sort($stored);
        $this->assertSame([100, 101, 102], array_map('intval', $stored));
    }

    /**
     * With an empty mirror the sync performs a full load of the history.
     *
     * MDL-UNIT-006: full load when the store is empty.
     */
    public function test_full_load_when_store_is_empty(): void {
        global $DB;
        $this->resetAfterTest();

        $this->sync_with([$this->make_response([
            $this->make_item(30),
            $this->make_item(20),
            $this->make_item(10),
        ])]);

        $this->assertSame(3, $DB->count_records('aiprovider_datacurso_consumption'));
    }

    /**
     * Running the sync twice over the same history does not duplicate rows.
     *
     * MDL-UNIT-006: the sync is idempotent.
     */
    public function test_running_sync_twice_does_not_duplicate(): void {
        global $DB;
        $this->resetAfterTest();

        $page = $this->make_response([$this->make_item(41), $this->make_item(40)]);
        $this->sync_with([$page]);
        $this->assertSame(2, $DB->count_records('aiprovider_datacurso_consumption'));

        $this->sync_with([$page]);
        $this->assertSame(2, $DB->count_records('aiprovider_datacurso_consumption'));
    }

    /**
     * Every field of an external consumption item is mapped onto the local record, and the
     * request asks the service for the history sorted by descending consumption id.
     *
     * API-CTR-003: interpretation of the consumption history response.
     */
    public function test_response_payload_maps_to_local_record(): void {
        global $DB;
        $this->resetAfterTest();

        $client = $this->sync_with([$this->make_response([
            $this->make_item(500, [
                'userid' => 42,
                'id_servicio' => 'local_forum_ai',
                'accion' => '/forum/execute',
                'cantidad_tokens' => 3.25,
                'saldo_restante' => 100.75,
                'created_at' => '2026-02-20 08:15:00',
            ]),
        ])]);

        $stored = $DB->get_record('aiprovider_datacurso_consumption', ['externalid' => 500]);
        $this->assertNotFalse($stored);
        $this->assertSame(42, (int) $stored->userid);
        $this->assertSame('local_forum_ai', $stored->service);
        $this->assertSame('/forum/execute', $stored->action);
        $this->assertEqualsWithDelta(3.25, (float) $stored->credits, 0.001);
        $this->assertEqualsWithDelta(100.75, (float) $stored->balance, 0.001);
        $this->assertSame(strtotime('2026-02-20 08:15:00'), (int) $stored->timecreated);

        $this->assertSame('/tokens/historial-consumos', $client->calls[0]['endpoint']);
        $this->assertSame('id_consumo', $client->calls[0]['params']['shor']);
        $this->assertSame('desc', $client->calls[0]['params']['shor_dir']);
    }

    /**
     * A valid date becomes a timestamp; an empty or unparseable one becomes zero without error.
     *
     * MDL-UNIT-005: date parsing during sync.
     */
    public function test_date_parsing_of_external_values(): void {
        global $DB;
        $this->resetAfterTest();

        $this->sync_with([$this->make_response([
            $this->make_item(3, ['created_at' => '2026-03-10 14:45:00']),
            $this->make_item(2, ['created_at' => '']),
            $this->make_item(1, ['created_at' => 'not a date at all']),
        ])]);

        $bytid = [];
        foreach ($DB->get_records('aiprovider_datacurso_consumption') as $row) {
            $bytid[(int) $row->externalid] = (int) $row->timecreated;
        }

        $this->assertSame(strtotime('2026-03-10 14:45:00'), $bytid[3]);
        $this->assertSame(0, $bytid[2]);
        $this->assertSame(0, $bytid[1]);
    }
}
