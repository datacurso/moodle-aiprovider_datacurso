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
 * Tests for the text summarisation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso;

use core_ai\aiactions\summarise_text;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the text summarisation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\process_summarise_text
 */
final class process_summarise_text_test extends \advanced_testcase {
    /** @var string|null Captured outgoing request body. */
    private ?string $capturedbody = null;

    /**
     * Install a Guzzle mock client (via core DI) and capture the outgoing request body.
     *
     * @param array $responses
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
     * Summarisation must read and send its own configured system instruction.
     *
     * MDL-UNIT-012: [Pendiente:fail] summarise inherits the text processor's reader, which uses
     * the wrong config key, so the summarise instruction ('action_summarise_text_instruction')
     * never reaches the payload. Red by design until the defect is fixed.
     */
    public function test_configured_summary_instruction_reaches_payload(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Key written by the provider admin settings form for the summarise action.
        set_config('action_summarise_text_instruction', 'SUMMARY_RULE', 'aiprovider_datacurso');

        $this->set_mock_http([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ])),
        ]);

        $action = new summarise_text(\context_system::instance()->id, (int) $USER->id, 'Long text to summarise');
        $processor = new class (new provider(), $action) extends process_summarise_text {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/chat/completions');
            }
        };
        $processor->process();

        $payload = json_decode((string) $this->capturedbody, true);
        $systemcontents = [];
        foreach ($payload['messages'] ?? [] as $message) {
            if (($message['role'] ?? '') === 'system') {
                $systemcontents[] = $message['content'];
            }
        }

        $this->assertContains('SUMMARY_RULE', $systemcontents);
    }
}
