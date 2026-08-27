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

use aiprovider_datacurso\httpclient\test_consumption_api_client;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../fixtures/test_consumption_api_client.php');

/**
 * Tests for consumption_history_service::get_consumption_history().
 *
 * The CSV export (amd/src/consumption.js) must download the FULL history, ignoring the
 * table filters: it calls this service with limit <= 0, which walks every remote page
 * server-side and returns everything in one response.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(consumption_history_service::class)]
final class consumption_history_service_test extends \advanced_testcase {
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
     * @return array
     */
    private function make_consumption(int $id): array {
        return [
            'id_consumo' => $id,
            'userid' => 0,
            'accion' => '/provider/chat/completions',
            'id_servicio' => 'aiprovider_datacurso',
            'cantidad_tokens' => 10,
            'saldo_restante' => 990,
            'created_at' => '2026-08-27 00:00:00',
        ];
    }

    /**
     * Build a successful API page response wrapping the given raw items.
     *
     * @param array $items Raw consumption items (as returned by make_consumption()).
     * @param int $totalpages
     * @return array
     */
    private function make_page_response(array $items, int $totalpages): array {
        return [
            'status' => 'success',
            'usuarios' => [
                ['consumos' => $items],
            ],
            'pagination' => [
                'current_page' => 1,
                'limit' => count($items),
                'total' => count($items),
                'total_pages' => $totalpages,
            ],
        ];
    }

    /**
     * limit <= 0 means "bring everything": the service must walk every remote page and
     * return the flattened result as a single, unpaginated page.
     */
    public function test_non_positive_limit_walks_every_page(): void {
        $client = new test_consumption_api_client();
        $client->responsesbypage = [
            1 => $this->make_page_response([$this->make_consumption(1), $this->make_consumption(2)], 3),
            2 => $this->make_page_response([$this->make_consumption(3), $this->make_consumption(4)], 3),
            3 => $this->make_page_response([$this->make_consumption(5)], 3),
        ];

        $result = consumption_history_service::get_consumption_history(
            page: 1,
            limit: 0,
            client: $client,
        );

        $this->assertSame('success', $result['status']);
        $this->assertCount(5, $result['consumption']);
        $this->assertSame([1, 2, 3, 4, 5], array_column($result['consumption'], 'id_consumption'));

        $this->assertSame(1, $result['pagination']['total_pages']);
        $this->assertSame(5, $result['pagination']['limit']);
        $this->assertSame(5, $result['pagination']['total']);

        $this->assertCount(3, $client->calls);
        $this->assertSame([1, 2, 3], array_column($client->calls, 'page'));
        $this->assertSame([500, 500, 500], array_column($client->calls, 'limit'));
    }

    /**
     * A positive limit keeps the original single-page behaviour: only one request is made
     * and the caller-provided limit is forwarded unchanged.
     */
    public function test_positive_limit_keeps_single_page_behaviour(): void {
        $client = new test_consumption_api_client();
        $client->responsesbypage = [
            1 => $this->make_page_response([$this->make_consumption(1)], 3),
        ];

        $result = consumption_history_service::get_consumption_history(
            page: 1,
            limit: 5,
            client: $client,
        );

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $result['consumption']);

        $this->assertSame(5, $result['pagination']['limit']);
        $this->assertSame(3, $result['pagination']['total_pages']);

        $this->assertCount(1, $client->calls);
        $this->assertSame(5, $client->calls[0]['limit']);
    }

    /**
     * A non-success API response is surfaced as a status error with the generic "no data"
     * message, without attempting to walk further pages.
     */
    public function test_non_success_response_returns_status_error(): void {
        $client = new test_consumption_api_client();
        $client->responsesbypage = [
            1 => ['status' => 'error'],
        ];

        $result = consumption_history_service::get_consumption_history(
            page: 1,
            limit: 10,
            client: $client,
        );

        $this->assertSame('error', $result['status']);
        $this->assertSame(get_string('nodata', 'aiprovider_datacurso'), $result['message']);
        $this->assertSame([], $result['consumption']);
        $this->assertCount(1, $client->calls);
    }
}
