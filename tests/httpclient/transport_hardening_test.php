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

namespace aiprovider_datacurso\httpclient;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../fixtures/fake_curl.php');

use aiprovider_datacurso\event\external_request_completed;
use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\transport_policy;
use aiprovider_datacurso\process_generate_text;
use aiprovider_datacurso\provider;
use core_ai\aiactions\generate_text;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests that every client sends its requests over verified HTTPS, never follows a redirect and
 * gives up after a total timeout.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\transport_policy
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api
 * @covers     \aiprovider_datacurso\abstract_processor
 */
final class transport_hardening_test extends \advanced_testcase {
    /** @var string License key configured for the tests. */
    private const LICENSE_KEY = 'transport-license-key-123';

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        set_config('licensekey', self::LICENSE_KEY, 'aiprovider_datacurso');
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, sha1(self::LICENSE_KEY), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
        unset($CFG->aiprovider_datacurso_allow_insecure_transport);
    }

    /**
     * Build an AI client on the given base URL whose transport is the given fake.
     *
     * @param fake_curl $curl Transport double.
     * @param string $baseurl Base URL of the service.
     * @return datacurso_api_base
     */
    private function make_ai_client(fake_curl $curl, string $baseurl = 'https://ai.example.invalid'): datacurso_api_base {
        return new class ($baseurl, $curl) extends datacurso_api_base {
            /**
             * Constructor.
             *
             * @param string $baseurl Base URL.
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                string $baseurl,
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct($baseurl);
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };
    }

    /**
     * Build a shop client whose transport is the given fake.
     *
     * @param fake_curl $curl Transport double.
     * @return datacurso_api
     */
    private function make_shop_client(fake_curl $curl): datacurso_api {
        return new class ($curl) extends datacurso_api {
            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct();
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };
    }

    /**
     * Build a text processor that posts to the given endpoint.
     *
     * @param string $endpoint Endpoint URL.
     * @return process_generate_text
     */
    private function make_processor(string $endpoint): process_generate_text {
        global $USER;
        $action = new generate_text(\context_system::instance()->id, (int) $USER->id, 'hello');
        return new class (new provider(), $action, $endpoint) extends process_generate_text {
            /**
             * Constructor.
             *
             * @param provider $provider Provider.
             * @param generate_text $action Action.
             * @param string $endpointurl Endpoint URL.
             */
            public function __construct(
                provider $provider,
                generate_text $action,
                /** @var string Endpoint URL. */
                private string $endpointurl
            ) {
                parent::__construct($provider, $action);
            }

            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri($this->endpointurl);
            }
        };
    }

    /**
     * Keep only the audit events among the captured ones.
     *
     * @param \phpunit_event_sink $sink Event sink.
     * @return external_request_completed[]
     */
    private function audit_events(\phpunit_event_sink $sink): array {
        return array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof external_request_completed
        ));
    }

    /**
     * Run the callback, expecting a moodle_exception, and return it.
     *
     * @param callable $callback Code that makes a request.
     * @return \moodle_exception
     */
    private function expect_exception(callable $callback): \moodle_exception {
        try {
            $callback();
        } catch (\moodle_exception $e) {
            $this->resetDebugging();
            return $e;
        }
        $this->fail('Expected a moodle_exception.');
    }

    /**
     * Assert that the cURL options verify the peer and its name, follow no redirect and set both timeouts.
     *
     * @param array $options Options the client passed to the cURL wrapper.
     * @param int $timeout Expected total timeout, in seconds.
     */
    private function assert_hardened_curl_options(array $options, int $timeout): void {
        $this->assertTrue($options['CURLOPT_SSL_VERIFYPEER']);
        $this->assertSame(2, $options['CURLOPT_SSL_VERIFYHOST']);
        $this->assertFalse($options['CURLOPT_FOLLOWLOCATION']);
        $this->assertSame(0, $options['CURLOPT_MAXREDIRS']);
        $this->assertSame(transport_policy::CONNECT_TIMEOUT, $options['CURLOPT_CONNECTTIMEOUT']);
        $this->assertSame($timeout, $options['CURLOPT_TIMEOUT']);
    }

    /**
     * An AI request verifies TLS, follows no redirect and gives up after the AI services timeout.
     */
    public function test_ai_request_sends_hardened_curl_options(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->make_ai_client($curl)->request('POST', '/forum/reply', ['message' => 'hi']);

        $this->assertCount(1, $curl->calls);
        $this->assert_hardened_curl_options($curl->calls[0]['options'], transport_policy::AI_SERVICES_TIMEOUT);
        $this->assertSame(300, transport_policy::AI_SERVICES_TIMEOUT);
    }

    /**
     * A shop request verifies TLS, follows no redirect and gives up after the shorter shop timeout.
     */
    public function test_shop_request_sends_hardened_curl_options(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"saldo": 1}');

        $this->make_shop_client($curl)->get('tokens/saldo');

        $this->assertCount(1, $curl->calls);
        $this->assert_hardened_curl_options($curl->calls[0]['options'], transport_policy::SHOP_TIMEOUT);
        $this->assertSame(30, transport_policy::SHOP_TIMEOUT);
    }

    /**
     * A redirect answer of an AI service is a failure: it is not followed, so the key goes nowhere else.
     */
    public function test_ai_redirect_answer_is_not_followed(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('Moved', 302);
        $curl->enqueue('{"ok": true}');
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(fn() => $this->make_ai_client($curl)->request('POST', '/forum/reply', []));

        $this->assertSame('httperror', $e->errorcode);
        $this->assertSame(302, $e->a);
        $this->assertCount(1, $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame(302, $events[0]->other['httpstatus']);
    }

    /**
     * A redirect answer of the shop is a failure and is not followed.
     */
    public function test_shop_redirect_answer_is_not_followed(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"saldo": 1}', 301);
        $curl->enqueue('{"saldo": 1}');

        $e = $this->expect_exception(fn() => $this->make_shop_client($curl)->get('tokens/saldo'));

        $this->assertSame('httperror', $e->errorcode);
        $this->assertSame(301, $e->a);
        $this->assertCount(1, $curl->calls);
    }

    /**
     * A plain HTTP base URL is refused before any connection, and logged as refused.
     */
    public function test_http_base_url_is_refused_without_a_connection(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(
            fn() => $this->make_ai_client($curl, 'http://ai.example.invalid')->request('POST', '/forum/reply', [])
        );

        $this->assertSame('insecuretransport', $e->errorcode);
        $this->assertSame([], $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('insecuretransport', $events[0]->other['errorcode']);
    }

    /**
     * The developer flag in config.php lets a local service be reached over plain HTTP, still hardened.
     */
    public function test_http_base_url_is_allowed_with_the_developer_flag(): void {
        global $CFG;
        $this->setAdminUser();
        $CFG->aiprovider_datacurso_allow_insecure_transport = true;
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $result = $this->make_ai_client($curl, 'http://ai.example.invalid')->request('POST', '/forum/reply', []);

        $this->assertSame(['ok' => true], $result);
        $this->assertCount(1, $curl->calls);
        $this->assert_hardened_curl_options($curl->calls[0]['options'], transport_policy::AI_SERVICES_TIMEOUT);
    }

    /**
     * Only http and https URLs are accepted, and http only with the developer flag.
     */
    public function test_require_secure_url_accepts_only_https_by_default(): void {
        global $CFG;
        transport_policy::require_secure_url('https://ai.example.invalid/x');
        transport_policy::require_secure_url('HTTPS://ai.example.invalid/x');

        foreach (['http://ai.example.invalid/x', 'ftp://ai.example.invalid/x', '/relative', ''] as $url) {
            $e = $this->expect_exception(fn() => transport_policy::require_secure_url($url));
            $this->assertSame('insecuretransport', $e->errorcode);
        }

        $CFG->aiprovider_datacurso_allow_insecure_transport = true;
        transport_policy::require_secure_url('http://ai.example.invalid/x');
        $e = $this->expect_exception(fn() => transport_policy::require_secure_url('ftp://ai.example.invalid/x'));
        $this->assertSame('insecuretransport', $e->errorcode);
    }

    /**
     * A download verifies TLS, follows no redirect, sends the key and stores the file.
     */
    public function test_download_sends_hardened_curl_options(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('package-bytes');

        $file = $this->make_ai_client($curl)->download_file('/resources/package', 'package.zip');

        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertSame('package-bytes', $file->get_content());
        $this->assertCount(1, $curl->calls);
        $this->assertSame('DOWNLOAD', $curl->calls[0]['method']);
        $this->assertSame('https://ai.example.invalid/resources/package', $curl->calls[0]['url']);
        $options = $curl->calls[0]['options'];
        $this->assert_hardened_curl_options($options, transport_policy::DOWNLOAD_TIMEOUT);
        $this->assertContains('License-Key: ' . self::LICENSE_KEY, $options['CURLOPT_HTTPHEADER']);
    }

    /**
     * A redirect answer to a download fails and stores nothing.
     */
    public function test_download_redirect_answer_is_not_followed(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('Moved', 302);
        $curl->enqueue('package-bytes');
        $sink = $this->redirectEvents();

        $this->expectException(\file_exception::class);
        try {
            $this->make_ai_client($curl)->download_file('/resources/package', 'package.zip');
        } finally {
            $this->assertDebuggingCalled();
            $this->assertCount(1, $curl->calls);
            $events = $this->audit_events($sink);
            $this->assertCount(1, $events);
            $this->assertSame('failure', $events[0]->other['result']);
        }
    }

    /**
     * A download without a license key is refused and logged, instead of sending an empty key.
     */
    public function test_download_without_license_key_is_refused(): void {
        $this->setAdminUser();
        unset_config('licensekey', 'aiprovider_datacurso');
        $curl = new fake_curl();
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(fn() => $this->make_ai_client($curl)->download_file('/resources/package', 'package.zip'));

        $this->assertSame('invalidlicensekey', $e->errorcode);
        $this->assertSame([], $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('invalidlicensekey', $events[0]->other['errorcode']);
    }

    /**
     * A download from a plain HTTP base URL is refused before any connection.
     */
    public function test_download_from_http_base_url_is_refused(): void {
        $this->setAdminUser();
        $curl = new fake_curl();

        $e = $this->expect_exception(
            fn() => $this->make_ai_client($curl, 'http://ai.example.invalid')->download_file('/resources/package', 'package.zip')
        );

        $this->assertSame('insecuretransport', $e->errorcode);
        $this->assertSame([], $curl->calls);
    }

    /**
     * The shop client built without a license key refuses the request and logs it as refused.
     */
    public function test_shop_without_license_key_is_refused_and_logged(): void {
        $this->setAdminUser();
        unset_config('licensekey', 'aiprovider_datacurso');
        $curl = new fake_curl();
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(fn() => $this->make_shop_client($curl)->get('tokens/saldo'));

        $this->assertSame('invalidlicensekey', $e->errorcode);
        $this->assertSame([], $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('shop', $events[0]->other['client']);
        $this->assertSame('tokens/saldo', $events[0]->other['endpoint']);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('invalidlicensekey', $events[0]->other['errorcode']);
    }

    /**
     * A core AI action verifies TLS, follows no redirect and sets both timeouts.
     */
    public function test_core_ai_request_sends_hardened_guzzle_options(): void {
        $this->setAdminUser();
        $captured = null;
        $mock = new MockHandler([
            function (RequestInterface $request, array $options) use (&$captured) {
                $captured = $options;
                return new Response(200, [], json_encode([
                    'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
                    'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
                ]));
            },
        ]);
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => HandlerStack::create($mock)]));

        $this->make_processor('https://example.invalid/provider/chat/completions')->process();

        $this->assertNotNull($captured);
        $this->assertTrue($captured['verify']);
        $this->assertFalse($captured['allow_redirects']);
        $this->assertSame(transport_policy::AI_SERVICES_TIMEOUT, $captured['timeout']);
        $this->assertSame(transport_policy::CONNECT_TIMEOUT, $captured['connect_timeout']);
    }

    /**
     * A redirect answer to a core AI action is a failure and the redirect target is never requested.
     */
    public function test_core_ai_redirect_answer_is_not_followed(): void {
        $this->setAdminUser();
        $mock = new MockHandler([
            new Response(302, ['Location' => 'http://elsewhere.example.invalid/steal']),
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']],
            ])),
        ]);
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => HandlerStack::create($mock)]));

        $result = $this->make_processor('https://example.invalid/provider/chat/completions')->process();

        $this->assertFalse($result->get_success());
        $this->assertSame(1, $mock->count());
        $this->resetDebugging();
    }

    /**
     * A core AI action towards a plain HTTP endpoint is refused before any connection.
     */
    public function test_core_ai_http_endpoint_is_refused(): void {
        $this->setAdminUser();
        $mock = new MockHandler([new Response(200, [], '{}')]);
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => HandlerStack::create($mock)]));
        $sink = $this->redirectEvents();

        $result = $this->make_processor('http://example.invalid/provider/chat/completions')->process();

        $this->assertDebuggingCalled();
        $this->assertFalse($result->get_success());
        $this->assertSame(1, $mock->count());
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('insecuretransport', $events[0]->other['errorcode']);
    }
}
