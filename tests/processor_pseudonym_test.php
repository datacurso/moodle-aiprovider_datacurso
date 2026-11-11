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

use aiprovider_datacurso\local\outbound_privacy;
use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * The core AI actions send the site-scoped pseudonym of the user, never the raw Moodle id.
 *
 * AIP-PRIV-001-R1: pseudonymous user identifier on the single outbound path of the processors
 * (abstract_processor::query_ai_api()).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\abstract_processor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_text::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_summarise_text::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_image::class)]
final class processor_pseudonym_test extends \advanced_testcase {
    /** @var provider The provider instance under test. */
    private provider $provider;

    /** @var array Requests sent through the mocked HTTP client. */
    private array $history = [];

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
     * Install a Guzzle mock client (via core DI) that answers the queued responses and records the requests.
     *
     * @param array $responses Queue of Response objects for the MockHandler.
     */
    private function set_mock_http(array $responses): void {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));
    }

    /**
     * Decode the JSON body of the first request sent.
     *
     * @return array
     */
    private function sent_body(): array {
        $this->assertNotEmpty($this->history, 'No request was sent.');
        return json_decode((string) $this->history[0]['request']->getBody(), true);
    }

    /**
     * Assert the body carries the pseudonym of the user and not the raw id.
     *
     * @param array $body Decoded request body.
     * @param int $userid Moodle id of the user the action is for.
     */
    private function assert_pseudonymous_userid(array $body, int $userid): void {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['userid']);
        $this->assertSame(outbound_privacy::pseudonymise_userid($userid), $body['userid']);
        $this->assertNotSame((string) $userid, $body['userid']);
    }

    /**
     * A text answer the processors accept.
     *
     * @return Response
     */
    private function text_answer(): Response {
        return new Response(200, [], json_encode([
            'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
        ]));
    }

    /**
     * Generate text sends the pseudonym of the user of the action.
     */
    public function test_generate_text_body_carries_pseudonymous_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_mock_http([$this->text_answer()]);

        $action = new generate_text(\context_system::instance()->id, (int) $user->id, 'Say hi');
        (new process_generate_text($this->provider, $action))->process();

        $this->assert_pseudonymous_userid($this->sent_body(), (int) $user->id);
    }

    /**
     * Summarise text sends the pseudonym of the user of the action.
     */
    public function test_summarise_text_body_carries_pseudonymous_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_mock_http([$this->text_answer()]);

        $action = new summarise_text(\context_system::instance()->id, (int) $user->id, 'Long text to summarise');
        (new process_summarise_text($this->provider, $action))->process();

        $this->assert_pseudonymous_userid($this->sent_body(), (int) $user->id);
    }

    /**
     * Generate image sends the pseudonym of the user of the action.
     */
    public function test_generate_image_body_carries_pseudonymous_userid(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->set_mock_http([new Response(500, [], '')]);

        $action = new generate_image(\context_system::instance()->id, (int) $user->id, 'A robot', 'hd', 'square', 1, 'vivid');
        $processor = new class ($this->provider, $action) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/images/generations');
            }
        };
        $processor->process();
        // The failed answer only reaches the developer debugging channel.
        $this->resetDebugging();

        $this->assert_pseudonymous_userid($this->sent_body(), (int) $user->id);
    }
}
