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

use core_ai\aiactions\explain_text;
use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use core_ai\aiactions\summarise_text;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Every failure of the provider reaches core_ai as a complete error response.
 *
 * core_ai builds the response object from the errorcode, error and errormessage keys returned
 * by the processor. When one of them was missing, the typed constructor threw a TypeError, the
 * action was not recorded and the user saw core's generic message instead of the provider's
 * one (for example the time when the rate limit resets).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\abstract_processor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_text::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_image::class)]
final class error_response_test extends \advanced_testcase {
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
        $handler = HandlerStack::create(new MockHandler($responses));
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $handler]));
    }

    /**
     * Build the processor of one of the text actions.
     *
     * @param string $actionname generate_text, summarise_text or explain_text.
     * @return abstract_processor
     */
    private function make_text_processor(string $actionname): abstract_processor {
        $actions = [
            'generate_text' => [generate_text::class, process_generate_text::class],
            'summarise_text' => [summarise_text::class, process_summarise_text::class],
            'explain_text' => [explain_text::class, process_explain_text::class],
        ];
        [$actionclass, $processorclass] = $actions[$actionname];
        $action = new $actionclass(contextid: 1, userid: 1, prompttext: 'Some content');
        return new $processorclass($this->provider, $action);
    }

    /**
     * Build an image processor with a fixed endpoint.
     *
     * @return process_generate_image
     */
    private function make_image_processor(): process_generate_image {
        global $USER;
        $action = new generate_image(\context_system::instance()->id, (int)$USER->id, 'A robot', 'hd', 'square', 1, 'vivid');
        return new class ($this->provider, $action) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/images/generations');
            }
        };
    }

    /**
     * Assert the response is a complete failure carrying the given code and message.
     *
     * @param \core_ai\aiactions\responses\response_base $response
     * @param int $errorcode
     * @param string $errormessage
     */
    private function assert_error_response($response, int $errorcode, string $errormessage): void {
        $this->assertFalse($response->get_success());
        $this->assertSame($errorcode, $response->get_errorcode());
        $this->assertSame(get_string('errorrequestfailed', 'aiprovider_datacurso'), $response->get_error());
        $this->assertSame($errormessage, $response->get_errormessage());
    }

    /**
     * Text actions combined with the service failures that must reach the user.
     *
     * @return array
     */
    public static function text_failure_provider(): array {
        $failures = [
            'rate limit' => [403, ['detail' => 'rate_limit_exceeded', 'reset_at' => 1893456000]],
            'credits exhausted' => [403, ['detail' => 'tokens_not_sufficient']],
            'license not allowed' => [403, ['detail' => 'license_not_allowed']],
            'server error' => [500, ['detail' => 'boom']],
        ];
        $cases = [];
        foreach (['generate_text', 'summarise_text', 'explain_text'] as $action) {
            foreach ($failures as $name => [$status, $body]) {
                $cases["{$action}, {$name}"] = [$action, $status, $body];
            }
        }
        return $cases;
    }

    /**
     * MDL-UNIT-011: a rejection of the service becomes a complete error response with its message.
     *
     * @param string $actionname The core_ai action.
     * @param int $status HTTP status returned by the service.
     * @param array $body JSON body returned by the service.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('text_failure_provider')]
    public function test_service_rejection_reaches_the_user(string $actionname, int $status, array $body): void {
        $this->set_mock_http([new Response($status, [], json_encode($body))]);

        $response = $this->make_text_processor($actionname)->process();

        $messages = [
            'rate_limit_exceeded' => get_string(
                'error_ratelimit_exceeded',
                'aiprovider_datacurso',
                userdate(1893456000, get_string('strftimedatetime', 'langconfig'))
            ),
            'tokens_not_sufficient' => get_string('notenoughtokens', 'aiprovider_datacurso'),
            'license_not_allowed' => get_string('license_not_allowed', 'aiprovider_datacurso'),
            'boom' => get_string('httperror', 'aiprovider_datacurso', 500),
        ];
        $this->assert_error_response($response, $status, $messages[$body['detail']]);
        $this->resetDebugging();
    }

    /**
     * MDL-UNIT-011: a connection failure becomes a complete error response.
     */
    public function test_connection_failure_reaches_the_user(): void {
        $this->set_mock_http([
            new ConnectException('down', new GuzzleRequest('POST', 'https://example.invalid')),
        ]);

        $response = $this->make_text_processor('explain_text')->process();

        $this->assert_error_response($response, 500, get_string('serviceunavailable', 'aiprovider_datacurso'));
        $this->resetDebugging();
    }

    /**
     * MDL-UNIT-011: a successful call without generated text becomes a complete error response.
     */
    public function test_text_response_without_content_reaches_the_user(): void {
        $this->set_mock_http([new Response(200, [], json_encode(['choices' => []]))]);

        $response = $this->make_text_processor('generate_text')->process();

        $this->assert_error_response($response, 400, get_string('responseinvalidai', 'aiprovider_datacurso'));
    }

    /**
     * MDL-UNIT-011: a successful call without an image becomes a complete error response.
     */
    public function test_image_response_without_data_reaches_the_user(): void {
        $this->set_mock_http([new Response(200, [], json_encode(['data' => []]))]);

        $response = $this->make_image_processor()->process();

        $this->assert_error_response($response, 400, get_string('responseinvalidaimage', 'aiprovider_datacurso'));
        $this->resetDebugging();
    }

    /**
     * MDL-UNIT-011: a rate limit rejection of the image service becomes a complete error response.
     */
    public function test_image_rate_limit_reaches_the_user(): void {
        $this->set_mock_http([new Response(403, [], json_encode(['detail' => 'tokens_not_sufficient']))]);

        $response = $this->make_image_processor()->process();

        $this->assert_error_response($response, 403, get_string('notenoughtokens', 'aiprovider_datacurso'));
    }
}
