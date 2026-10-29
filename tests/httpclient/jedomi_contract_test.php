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

/**
 * Contract of a call to /jedomi/v1/chat/completions on plugins-ai through the AI services client.
 *
 * Pins the headers and body fields that plugins-ai requires from a Jedomi request, and how its
 * error answers are turned into exceptions.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\ai_services_api
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 */
final class jedomi_contract_test extends \advanced_testcase {
    /** @var string Jedomi chat completions route of plugins-ai. */
    private const PATH = '/jedomi/v1/chat/completions';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        set_config('licensekey', 'jedomi-test-key', 'aiprovider_datacurso');
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
    }

    /**
     * Build the AI services client Jedomi uses, with the given transport.
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
                parent::__construct(null, 'https://ai.example.invalid', 'https://eu.ai.example.invalid', 'local_jedomi');
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };
    }

    /**
     * Send a Jedomi chat request answered with the given body and status, and return the exception raised.
     *
     * @param string $body Body of the answer.
     * @param int $httpcode HTTP status of the answer.
     * @return \moodle_exception
     */
    private function call_answered_with(string $body, int $httpcode): \moodle_exception {
        $curl = new fake_curl();
        $curl->enqueue($body, $httpcode);

        try {
            $this->make_client($curl)->request('POST', self::PATH, ['model' => 'm', 'messages' => []]);
        } catch (\moodle_exception $e) {
            $this->resetDebugging();
            return $e;
        }

        $this->fail('Expected a moodle_exception.');
    }

    /**
     * I4: the call carries the license, Jedomi's rate-limit headers, the plugin headers and the identity fields.
     */
    public function test_chat_call_sends_the_headers_and_fields_plugins_ai_requires(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user(['timezone' => 'America/Bogota', 'lang' => 'en']);
        $this->setUser($user);
        set_config('ratelimit_local_jedomi_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_jedomi_limit', 40, 'aiprovider_datacurso');
        set_config('ratelimit_local_jedomi_window', json_encode(['value' => 1, 'unit' => 'hours']), 'aiprovider_datacurso');
        $jedomi = \core_plugin_manager::instance()->get_plugin_info('local_jedomi');
        $this->assertNotNull($jedomi, 'local_jedomi must be installed to check its plugin headers.');

        $curl = new fake_curl();
        $curl->enqueue('{"choices": [{"message": {"role": "assistant", "content": "Hi"}, "finish_reason": "stop"}]}');
        $messages = [
            ['role' => 'system', 'content' => 'You are Jedomi.'],
            ['role' => 'user', 'content' => 'Hello'],
        ];

        $answer = $this->make_client($curl)->request('POST', self::PATH, [
            'model' => 'Qwen/Qwen3-235B-A22B-Instruct-2507',
            'messages' => $messages,
            'stream' => false,
            'userid' => (int) $user->id,
        ]);

        $this->assertSame('Hi', $answer['choices'][0]['message']['content']);
        $this->assertCount(1, $curl->calls);
        $call = $curl->calls[0];
        $this->assertSame('POST', $call['method']);
        $this->assertSame('https://ai.example.invalid' . self::PATH, $call['url']);

        $headers = $call['options']['CURLOPT_HTTPHEADER'];
        $this->assertContains('Content-Type: application/json', $headers);
        $this->assertContains('License-Key: jedomi-test-key', $headers);
        $this->assertContains('X-RateLimit-Enable: 1', $headers);
        $this->assertContains('X-RateLimit-Limit: 40', $headers);
        $this->assertContains('X-RateLimit-WindowSeconds: 3600', $headers);
        $this->assertContains('X-RateLimit-MaxPerAction: 2', $headers);
        $this->assertContains('X-Plugin-Name: local_jedomi', $headers);
        $this->assertContains('X-Plugin-Version: ' . $jedomi->release, $headers);
        $this->assertContains('X-Plugin-Build: ' . $jedomi->versiondisk, $headers);

        $body = json_decode($call['params'], true);
        $this->assertSame('Qwen/Qwen3-235B-A22B-Instruct-2507', $body['model']);
        $this->assertSame($messages, $body['messages']);
        $this->assertFalse($body['stream']);
        $this->assertSame(outbound_privacy::pseudonymise_userid($user->id), $body['userid']);
        $this->assertNotSame((string) $user->id, $body['userid']);
        $this->assertSame(datacurso_api_base::get_site_uuid(), $body['site_id']);
        $this->assertSame('en', $body['lang']);
        $this->assertSame('America/Bogota', $body['timezone']);
        $this->assertSame($CFG->wwwroot, $body['site_url']);
    }

    /**
     * I4: with Jedomi's limit disabled no rate-limit header is sent.
     */
    public function test_chat_call_without_a_jedomi_limit_sends_no_rate_limit_headers(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"choices": []}');

        $this->make_client($curl)->request('POST', self::PATH, ['model' => 'm', 'messages' => []]);

        foreach ($curl->calls[0]['options']['CURLOPT_HTTPHEADER'] as $header) {
            $this->assertStringStartsNotWith('X-RateLimit-', $header);
        }
    }

    /**
     * Error answers of plugins-ai for Jedomi and the exception each one becomes.
     *
     * @return array
     */
    public static function error_answer_provider(): array {
        return [
            '400 invalid model' => [
                '{"detail": {"code": "jedomi_invalid_model", "message": "Model \'x\' is not available for Jedomi"}}',
                400, 'httperror', 400,
            ],
            '403 no credit' => [
                '{"detail": "tokens_not_sufficient", "current_balance": 0, "min_required_tokens": 2}',
                403, 'notenoughtokens', null,
            ],
            '403 credit limit of the window' => [
                '{"detail": "rate_limit_exceeded", "reset_at": 0}',
                403, 'error_ratelimit_exceeded', '',
            ],
            '429 provider rate limit' => [
                '{"detail": {"code": "jedomi_rate_limited", "message": "The AI provider rate limit was reached"}}',
                429, 'httperror', 429,
            ],
            '502 provider unavailable' => [
                '{"detail": {"code": "jedomi_provider_unavailable", "message": "The AI provider is unavailable"}}',
                502, 'httperror', 502,
            ],
            '504 provider timeout' => [
                '{"detail": {"code": "jedomi_timeout", "message": "The AI provider did not respond in time"}}',
                504, 'httperror', 504,
            ],
        ];
    }

    /**
     * I5: each error answer of plugins-ai becomes its provider exception, without the body in the message.
     *
     * @dataProvider error_answer_provider
     * @param string $body Body of the answer.
     * @param int $httpcode HTTP status of the answer.
     * @param string $errorcode Expected error code.
     * @param mixed $a Expected exception parameter.
     */
    public function test_error_answers_become_provider_exceptions(string $body, int $httpcode, string $errorcode, $a): void {
        $this->setAdminUser();

        $e = $this->call_answered_with($body, $httpcode);

        $this->assertSame($errorcode, $e->errorcode);
        $this->assertSame('aiprovider_datacurso', $e->module);
        $this->assertSame($a, $e->a);
        $this->assertStringNotContainsString('jedomi_', $e->getMessage());
        $this->assertStringNotContainsString('not available for Jedomi', $e->getMessage());
    }
}
