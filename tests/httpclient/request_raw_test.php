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

require_once(__DIR__ . '/../fixtures/fake_curl.php');

use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\outbound_privacy;
use aiprovider_datacurso\provider;

/**
 * Tests for request_raw(): a JSON request whose successful answer is returned undecoded.
 *
 * The request must be the one request() sends (headers, identity fields, rate limit, audit log) and
 * error answers must raise the same exceptions; only the body of a successful answer is kept as is.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(datacurso_api_base::class)]
final class request_raw_test extends \advanced_testcase {
    /** @var string Jedomi text-to-speech route of plugins-ai, which answers audio/mpeg. */
    private const PATH = '/jedomi/v1/audio/speech';

    /** @var string Body of a fake MP3 answer, with bytes that are not valid UTF-8 nor JSON. */
    private const MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00\xff\xfb\x90\x64 fake mp3 frames";

    /** @var string License key of the provider instance used by the tests. */
    private const LICENSE_KEY = 'raw-test-key';

    /** @var provider Enabled provider instance holding the license key. */
    private provider $provider;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->create_instance();
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
    }

    /**
     * Create the enabled provider instance the clients read their license key and rate limits from.
     *
     * Moodle 5.0 keeps the license key and the rate limits in the provider instance config, not in the plugin config.
     */
    private function create_instance(): void {
        global $DB;
        $this->provider = (new \core_ai\manager($DB))->create_provider_instance(
            classname: provider::class,
            name: 'test',
            enabled: true,
            config: ['licensekey' => self::LICENSE_KEY],
        );
    }

    /**
     * Replace the config of the provider instance, keeping its license key.
     *
     * @param array $config Config of the instance, besides the license key.
     */
    private function configure_instance(array $config): void {
        global $DB;
        $this->provider = (new \core_ai\manager($DB))->update_provider_instance(
            provider: $this->provider,
            config: ['licensekey' => self::LICENSE_KEY] + $config,
        );
    }

    /**
     * Build an AI services client with the given transport.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_services_api
     */
    private function make_client(fake_curl $curl): ai_services_api {
        return new class ($curl) extends ai_services_api {
            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct(null, 'https://ai.example.invalid', 'https://eu.ai.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };
    }

    /**
     * Body of a speech request, as Jedomi sends it.
     *
     * @param int $userid Moodle id of the user the audio is read for.
     * @return array
     */
    private function speech_body(int $userid): array {
        return ['model' => 'jedomi/Qwen/Qwen3-TTS', 'input' => 'Hola, soy Jedomi.', 'voice' => 'Vivian', 'userid' => $userid];
    }

    /**
     * A 200 binary answer comes back byte for byte, without a decoding error, and is logged as a success.
     */
    public function test_binary_body_of_a_successful_answer_is_returned_unchanged(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $curl = new fake_curl();
        $curl->enqueue(self::MP3);
        $sink = $this->redirectEvents();

        $audio = $this->make_client($curl)->request_raw('POST', self::PATH, $this->speech_body((int) $user->id));

        $this->assertSame(self::MP3, $audio);
        $this->assertCount(1, $curl->calls);
        $this->assertDebuggingNotCalled();
        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof \aiprovider_datacurso\event\external_request_completed
        ));
        $sink->close();
        $this->assertCount(1, $events);
        $this->assertSame('success', $events[0]->other['result']);
        $this->assertSame('local_jedomi', $events[0]->other['service']);
        $this->assertSame(self::PATH, $events[0]->other['endpoint']);
    }

    /**
     * A successful JSON body is not decoded either: the caller decides what the body means.
     */
    public function test_json_body_of_a_successful_answer_is_returned_undecoded(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->assertSame('{"ok": true}', $this->make_client($curl)->request_raw('POST', self::PATH, ['input' => 'x']));
    }

    /**
     * The raw call sends exactly the request a JSON call sends: URL, method, headers and identity fields.
     */
    public function test_request_is_identical_to_a_json_request(): void {
        $user = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $this->setUser($user);
        $this->configure_instance([
            'ratelimit_local_jedomi_enable' => 1,
            'ratelimit_local_jedomi_limit' => 40,
            'ratelimit_local_jedomi_window_value' => 1,
            'ratelimit_local_jedomi_window_unit' => 'hours',
        ]);
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $curl->enqueue(self::MP3);
        $client = $this->make_client($curl);
        $body = $this->speech_body((int) $user->id);

        $client->request('POST', self::PATH, $body);
        $client->request_raw('POST', self::PATH, $body);

        [$json, $raw] = $curl->calls;
        $this->assertSame($json['method'], $raw['method']);
        $this->assertSame('https://ai.example.invalid' . self::PATH, $raw['url']);
        $this->assertSame($json['url'], $raw['url']);
        $this->assertSame($json['options'], $raw['options']);
        $this->assertSame($json['params'], $raw['params']);

        $headers = $raw['options']['CURLOPT_HTTPHEADER'];
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('License-Key: raw-test-key', $headers);
        $this->assertContains('X-RateLimit-Enable: 1', $headers);
        $this->assertContains('X-RateLimit-Limit: 40', $headers);
        $this->assertContains('X-RateLimit-MaxPerAction: 2', $headers);

        $sent = json_decode($raw['params'], true);
        // The numeric id of the body leaves the site only as its pseudonym, on both paths.
        $this->assertSame(outbound_privacy::pseudonymise_userid($user->id), $sent['userid']);
        $this->assertSame(datacurso_api_base::get_site_uuid(), $sent['site_id']);
        $this->assertSame('en', $sent['lang']);
        $this->assertSame('Hola, soy Jedomi.', $sent['input']);
    }

    /**
     * Error answers of the AI services, which are JSON bodies.
     *
     * @return array
     */
    public static function error_answer_provider(): array {
        return [
            '400 invalid voice' => ['{"detail": {"code": "jedomi_invalid_voice", "message": "internal"}}', 400],
            '403 no credit' => ['{"detail": "tokens_not_sufficient", "current_balance": 0}', 403],
            '403 credit limit of the window' => ['{"detail": "rate_limit_exceeded", "reset_at": 0}', 403],
            '403 license not allowed' => ['{"detail": "license_not_allowed"}', 403],
            '426 plugin update required' => [
                '{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "local_jedomi",'
                    . ' "version": "1.0.0", "minimum": "2.0.0", "string_id": "plugin_update_required", "string": "x"}}',
                426,
            ],
            '502 provider unavailable' => ['{"detail": {"code": "jedomi_provider_unavailable", "message": "x"}}', 502],
        ];
    }

    /**
     * Each error answer raises in request_raw() the exception request() raises for it.
     *
     * @param string $body Body of the answer.
     * @param int $httpcode HTTP status of the answer.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('error_answer_provider')]
    public function test_error_answers_raise_like_a_json_request(string $body, int $httpcode): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue($body, $httpcode);
        $curl->enqueue($body, $httpcode);
        $client = $this->make_client($curl);

        $expected = $this->exception_of(fn() => $client->request('POST', self::PATH, ['input' => 'x']));
        $actual = $this->exception_of(fn() => $client->request_raw('POST', self::PATH, ['input' => 'x']));

        $this->assertSame('aiprovider_datacurso', $actual->module);
        $this->assertSame($expected->errorcode, $actual->errorcode);
        $this->assertEquals($expected->a, $actual->a);
        $this->assertSame($expected->getMessage(), $actual->getMessage());
        $this->assertStringNotContainsString('jedomi_', $actual->getMessage());
        // The 426 answer reports the unsupported release through debugging(), once per request.
        $this->resetDebugging();
    }

    /**
     * A refused request (plain HTTP base URL) raises before any connection, like a JSON request.
     *
     * On Moodle 5.0 a disabled provider is refused when the client is built (instance_disabled), so the
     * request-time refusal exercised here is the transport policy.
     */
    public function test_refused_request_never_reaches_the_service(): void {
        global $CFG;
        $this->setAdminUser();
        unset($CFG->aiprovider_datacurso_allow_insecure_transport);
        $curl = new fake_curl();
        $client = new class ($curl) extends ai_services_api {
            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct(null, 'http://ai.example.invalid', 'http://eu.ai.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };

        $e = $this->exception_of(fn() => $client->request_raw('POST', self::PATH, ['input' => 'x']));

        $this->assertSame('insecuretransport', $e->errorcode);
        $this->assertSame([], $curl->calls);
    }

    /**
     * Run a call that must fail and return its exception.
     *
     * @param callable $call Call to run.
     * @return \moodle_exception
     */
    private function exception_of(callable $call): \moodle_exception {
        try {
            $call();
        } catch (\moodle_exception $e) {
            $this->resetDebugging();
            return $e;
        }
        $this->fail('Expected a moodle_exception.');
    }
}
