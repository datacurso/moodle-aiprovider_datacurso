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
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for {@see abstract_processor::handle_api_error()} and the TransferException catch in
 * {@see abstract_processor::query_ai_api()}, as exercised through the concrete
 * {@see process_generate_text} processor.
 *
 * These tests assert the security-hardening contract: actionable 403 rejections (rate limit,
 * insufficient credits, license not allowed) surface a localized message, while every other
 * upstream error (generic HTTP error, non-JSON body, network failure) is sanitized before it
 * reaches the end user -- the raw upstream text only reaches the developer debugging channel.
 *
 * Ported from the 4.5 security-hardening release (1.5.1) and re-implemented against this
 * branch's harness: a real seeded provider instance (see {@see \core_ai\manager}) instead of
 * `new provider()`, since {@see provider} extends `\core_ai\provider`, whose constructor
 * requires a persisted instance record.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\abstract_processor::class)]
final class process_generate_text_errors_test extends \advanced_testcase {
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
     * Install a Guzzle mock client (via core DI) that returns the queued responses.
     *
     * @param array $responses Queue of Response/exception objects for the MockHandler.
     */
    private function set_mock_http(array $responses): void {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));
    }

    /**
     * Build a testable processor for a fixed prompt.
     *
     * @return process_generate_text
     */
    private function make_processor(): process_generate_text {
        $action = new generate_text(contextid: 1, userid: 1, prompttext: 'hi');
        return new process_generate_text($this->provider, $action);
    }

    /**
     * A 403 credit exhaustion rejection surfaces the actionable localized message.
     */
    public function test_403_insufficient_credits_is_actionable(): void {
        $this->set_mock_http([
            new Response(403, [], json_encode(['detail' => 'tokens_not_sufficient'])),
        ]);

        $response = $this->make_processor()->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(403, $response->get_errorcode());
        $this->assertSame(get_string('notenoughtokens', 'aiprovider_datacurso'), $response->get_errormessage());
        $this->assertStringNotContainsString('tokens_not_sufficient', $response->get_errormessage());
    }

    /**
     * A 403 license rejection surfaces the actionable localized message.
     */
    public function test_403_license_not_allowed_is_actionable(): void {
        $this->set_mock_http([
            new Response(403, [], json_encode(['detail' => 'license_not_allowed'])),
        ]);

        $response = $this->make_processor()->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(403, $response->get_errorcode());
        $this->assertSame(get_string('license_not_allowed', 'aiprovider_datacurso'), $response->get_errormessage());
        $this->assertStringNotContainsString('license_not_allowed', $response->get_errormessage());
    }

    /**
     * A generic service error must not leak the upstream detail to the end user.
     *
     * The localized httperror string is returned instead, and the upstream message only reaches
     * the developer debugging channel.
     */
    public function test_generic_service_error_is_sanitized(): void {
        $this->set_mock_http([
            new Response(500, [], 'boom'),
        ]);

        $response = $this->make_processor()->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(500, $response->get_errorcode());
        $this->assertSame(get_string('httperror', 'aiprovider_datacurso', 500), $response->get_errormessage());
        $this->assertStringNotContainsString('boom', $response->get_errormessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalledCount(1);
        $this->assertStringContainsString('boom', $debugmessages[0]->message);
    }

    /**
     * A non-JSON error body (e.g. a gateway HTML page) is never echoed to the end user.
     */
    public function test_non_json_error_body_is_not_surfaced(): void {
        $this->set_mock_http([
            new Response(502, ['Content-Type' => 'text/html'], '<html>gateway secret</html>'),
        ]);

        $response = $this->make_processor()->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(502, $response->get_errorcode());
        $this->assertSame(get_string('httperror', 'aiprovider_datacurso', 502), $response->get_errormessage());
        $this->assertStringNotContainsString('gateway secret', $response->get_errormessage());
        $this->assertStringNotContainsString('<html>', $response->get_errormessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalledCount(1);
        $this->assertStringContainsString('gateway secret', $debugmessages[0]->message);
    }

    /**
     * A network exception is caught and surfaced with a code and a localized message only.
     *
     * The raw transport message (which may contain hostnames) is confined to debugging output.
     */
    public function test_network_error_is_sanitized(): void {
        $this->set_mock_http([
            new ConnectException('down', new GuzzleRequest('POST', 'https://example.invalid/provider/chat/completions')),
        ]);

        $response = $this->make_processor()->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(500, $response->get_errorcode());
        $this->assertSame(get_string('serviceunavailable', 'aiprovider_datacurso'), $response->get_errormessage());
        $this->assertStringNotContainsString('down', $response->get_errormessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalledCount(1);
        $this->assertStringContainsString('down', $debugmessages[0]->message);
    }
}
