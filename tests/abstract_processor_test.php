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

use core_ai\aiactions\generate_text;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/test_processor.php');

/**
 * Tests for the abstract processor's rate-limiter construction.
 *
 * classes/local/ratelimiter.php's constructor requires the instance provider
 * (D2), so any processor that still builds it with `new ratelimiter()` fatals
 * with an ArgumentCountError on every real API call. Uses {@see test_processor},
 * a fixed-endpoint stub, so the test exercises only abstract_processor and stays
 * network-free (datacurso_api_base::is_for_ue() would otherwise reach the network).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\abstract_processor::class)]
final class abstract_processor_test extends \advanced_testcase {
    /** @var provider The provider instance under test. */
    private provider $provider;

    /**
     * Seed an enabled provider instance with a license key.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $manager = new \core_ai\manager($DB);
        $this->provider = $manager->create_provider_instance(
            classname: provider::class,
            name: 'test',
            enabled: true,
            config: ['licensekey' => 'test-key'],
        );
    }

    /**
     * Build a minimal generate_text action.
     *
     * @return generate_text
     */
    private function make_action(): generate_text {
        return new generate_text(
            contextid: 1,
            userid: 1,
            prompttext: 'Hello',
        );
    }

    /**
     * query_ai_api must not fatal with an ArgumentCountError when it builds its
     * ratelimiter, because the ratelimiter constructor now requires the instance
     * provider.
     */
    public function test_query_ai_api_builds_the_ratelimiter_with_the_instance_provider(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(200, ['Content-Type' => 'application/json'], 'ok'));

        $processor = new test_processor($this->provider, $this->make_action());
        $method = new \ReflectionMethod($processor, 'query_ai_api');

        $result = $method->invoke($processor);

        $this->assertTrue($result['success']);
        $this->assertSame('ok', $result['generatedcontent']);
    }

    /**
     * A connection-level failure (DNS/timeout/refused) throws Guzzle's ConnectException, not
     * RequestException. query_ai_api() must catch the wider TransferException so this results in
     * the graceful error response instead of an uncaught exception reaching the caller.
     */
    public function test_query_ai_api_returns_a_graceful_error_when_the_connection_fails(): void {
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new ConnectException(
            'Could not resolve host',
            new Request('POST', 'https://example.invalid/provider/chat/completions'),
        ));

        $processor = new test_processor($this->provider, $this->make_action());
        $method = new \ReflectionMethod($processor, 'query_ai_api');

        $result = $method->invoke($processor);

        $this->assertFalse($result['success']);
        $this->assertSame('Could not resolve host', $result['errormessage']);
    }
}
