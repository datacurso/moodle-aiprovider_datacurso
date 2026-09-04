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

/**
 * Tests for the text generation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso;

use core_ai\aiactions\generate_text;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the text generation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\process_generate_text
 */
final class process_generate_text_test extends \advanced_testcase {
    /** @var string|null Captured outgoing request body. */
    private ?string $capturedbody = null;

    /**
     * Install a Guzzle mock client (via core DI) that returns the queued responses and
     * captures the outgoing request body.
     *
     * @param array $responses Queue of Response/exception objects for the MockHandler.
     */
    private function set_mock_http(array $responses): void {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::tap(function (RequestInterface $request): void {
            $this->capturedbody = (string) $request->getBody();
        }));
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));
    }

    /**
     * Build a testable processor for a given prompt.
     *
     * The processor is an anonymous subclass returning a fixed endpoint, avoiding any
     * network at construction, and exposing the protected success parser for unit testing.
     *
     * @param string $prompt
     * @return process_generate_text
     */
    private function make_processor(string $prompt): process_generate_text {
        global $USER;
        $action = new generate_text(\context_system::instance()->id, (int) $USER->id, $prompt);
        return new class (new provider(), $action) extends process_generate_text {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/chat/completions');
            }

            /**
             * Public wrapper over the protected success parser, for direct unit testing.
             *
             * @param ResponseInterface $response
             * @return array
             */
            public function expose_handle_api_success(ResponseInterface $response): array {
                return $this->handle_api_success($response);
            }
        };
    }

    /**
     * A valid response with content yields the generated text and finish reason.
     *
     * MDL-UNIT-009: parsing of the text generation response (valid body).
     */
    public function test_parse_valid_response(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $response = new Response(200, [], json_encode([
            'id' => 'x',
            'system_fingerprint' => 'fp',
            'choices' => [['message' => ['content' => 'HELLO'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
        ]));

        $result = $this->make_processor('hi')->expose_handle_api_success($response);

        $this->assertTrue($result['success']);
        $this->assertSame('HELLO', $result['generatedcontent']);
        $this->assertSame('stop', $result['finishreason']);
    }

    /**
     * An empty / content-less body yields the invalid-response error.
     *
     * MDL-UNIT-009: parsing of the text generation response (empty body).
     */
    public function test_parse_empty_response(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $response = new Response(200, [], json_encode([]));

        $result = $this->make_processor('hi')->expose_handle_api_success($response);

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('responseinvalidai', 'aiprovider_datacurso'), $result['error']);
    }

    /**
     * A 403 rate-limit rejection surfaces a localized retry message.
     *
     * MDL-UNIT-011: error handling (rate limit exceeded).
     */
    public function test_rate_limit_error_is_localized(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->set_mock_http([
            new Response(403, [], json_encode(['detail' => 'rate_limit_exceeded', 'reset_at' => time() + 3600])),
        ]);

        $response = $this->make_processor('hi')->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(403, $response->get_errorcode());
        $this->assertStringContainsString('exceeded', $response->get_errormessage());
    }

    /**
     * A generic service error surfaces the received detail.
     *
     * MDL-UNIT-011: error handling (generic service error).
     */
    public function test_generic_service_error_surfaces_detail(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->set_mock_http([
            new Response(500, [], json_encode(['error' => ['message' => 'boom']])),
        ]);

        $response = $this->make_processor('hi')->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(500, $response->get_errorcode());
        $this->assertSame('boom', $response->get_errormessage());
    }

    /**
     * A network exception is caught and surfaced with a code and message.
     *
     * MDL-UNIT-011: error handling (network failure).
     */
    public function test_network_error_is_surfaced(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // A real connection failure throws ConnectException (a sibling of RequestException, both
        // extending TransferException). The processor catches TransferException, so this proves the
        // connect failure is surfaced gracefully instead of bubbling up.
        $this->set_mock_http([
            new ConnectException('down', new GuzzleRequest('POST', 'https://example.invalid')),
        ]);

        $response = $this->make_processor('hi')->process();

        $this->assertFalse($response->get_success());
        $this->assertSame(500, $response->get_errorcode());
        $this->assertSame('down', $response->get_errormessage());
    }

    /**
     * The admin-configured system instruction must reach the payload.
     *
     * MDL-UNIT-012: [Pendiente:fail] the processor reads config key
     * 'action_generate_text_systeminstruction' while the admin form writes
     * 'action_generate_text_instruction', so the instruction never reaches the model.
     * This test asserts the correct behavior and is red by design until the defect is fixed.
     */
    public function test_configured_system_instruction_reaches_payload(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Key written by the provider admin settings form (provider::get_action_settings).
        set_config('action_generate_text_instruction', 'ADMIN_RULE', 'aiprovider_datacurso');

        $this->set_mock_http([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ])),
        ]);

        $this->make_processor('Say hi')->process();

        $payload = json_decode((string) $this->capturedbody, true);
        $systemcontents = [];
        foreach ($payload['messages'] ?? [] as $message) {
            if (($message['role'] ?? '') === 'system') {
                $systemcontents[] = $message['content'];
            }
        }

        $this->assertContains('ADMIN_RULE', $systemcontents);
    }

    /**
     * The request body carries the system instruction then the user prompt, in order.
     *
     * API-CTR-002: [Pendiente:fail] payload ordering depends on the system-instruction defect
     * (see MDL-UNIT-012); the instruction arrives empty, so this test is red by design.
     */
    public function test_payload_has_system_then_user_message(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        set_config('action_generate_text_instruction', 'ADMIN_RULE', 'aiprovider_datacurso');

        $this->set_mock_http([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ])),
        ]);

        $this->make_processor('Say hi')->process();

        $payload = json_decode((string) $this->capturedbody, true);
        $messages = $payload['messages'] ?? [];

        $this->assertSame('system', $messages[0]['role'] ?? null);
        $this->assertSame('ADMIN_RULE', $messages[0]['content'] ?? null);
        $this->assertSame('user', $messages[1]['role'] ?? null);
        $this->assertSame('Say hi', $messages[1]['content'] ?? null);
    }
}
