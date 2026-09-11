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

/**
 * Tests for the Datacurso shop API client error handling.
 *
 * The shop client must never leak the request URL, the response body or the
 * transport error text (which may contain hostnames) into exception messages.
 *
 * Ported from the 4.5 security-hardening release (1.5.1). The missing-license-key test is
 * retargeted from 4.5's `invalidlicensekey` (used there for this exact constructor path; on this
 * branch the constructor validates against a real seeded provider instance instead of
 * `get_config()`, so it is never the right error here -- `invalidlicensekey` still exists on this
 * branch, but only for datacurso_api_base::send_request()'s own unrelated pre-flight check) to
 * this branch's own two failure modes for datacurso_api: no enabled instance at all
 * (`instance_disabled`), and an enabled instance with no license key configured
 * (`licensekey_missing`).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api
 */
final class datacurso_api_test extends \advanced_testcase {
    /**
     * Seed an enabled aiprovider_datacurso instance with the given config.
     *
     * @param array $config
     */
    private function seed_instance(array $config): void {
        global $DB;
        $manager = new \core_ai\manager($DB);
        $manager->create_provider_instance(
            classname: \aiprovider_datacurso\provider::class,
            name: 'test',
            enabled: true,
            config: $config,
        );
    }

    /**
     * Build a shop client whose transport is the given fake cURL wrapper.
     *
     * @param fake_curl $curl
     * @return datacurso_api
     */
    private function make_client(fake_curl $curl): datacurso_api {
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
     * Run a GET expecting a moodle_exception, and return it for inspection.
     *
     * @param datacurso_api $client
     * @return \moodle_exception
     */
    private function get_expecting_exception(datacurso_api $client): \moodle_exception {
        try {
            $client->get('tokens/saldo');
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('Expected a moodle_exception.');
    }

    /**
     * With no enabled provider instance at all, the localized instance_disabled exception is raised.
     */
    public function test_no_enabled_instance_raises_localized_exception(): void {
        $this->resetAfterTest();

        try {
            new datacurso_api();
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('instance_disabled', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
        }
    }

    /**
     * An enabled instance with no license key configured raises licensekey_missing.
     */
    public function test_missing_license_key_raises_localized_exception(): void {
        $this->resetAfterTest();
        $this->seed_instance([]);

        try {
            new datacurso_api();
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('licensekey_missing', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
        }
    }

    /**
     * A transport failure is reported with the cURL error number only: no hostnames leak.
     */
    public function test_curl_failure_hides_transport_details(): void {
        $this->resetAfterTest();
        $this->seed_instance(['licensekey' => 'test-key']);

        $curl = new fake_curl();
        $curl->enqueue('', 0, 'Could not resolve host: shop.datacurso.com', 6);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('curlerror', $e->errorcode);
        $this->assertSame('aiprovider_datacurso', $e->module);
        $this->assertSame(6, $e->a);
        $this->assertStringNotContainsString('shop.datacurso.com', $e->getMessage());
        $this->assertStringNotContainsString('Could not resolve', $e->getMessage());
        $this->resetDebugging();
    }

    /**
     * An HTTP error is reported with the status code only: neither the URL nor the body leak.
     */
    public function test_http_error_hides_url_and_body(): void {
        $this->resetAfterTest();
        $this->seed_instance(['licensekey' => 'test-key']);

        $curl = new fake_curl();
        $curl->enqueue('secret-body', 500);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('httperror', $e->errorcode);
        $this->assertSame('aiprovider_datacurso', $e->module);
        $this->assertSame(500, $e->a);
        $this->assertStringNotContainsString('secret-body', $e->getMessage());
        $this->assertStringNotContainsString('shop.datacurso.com', $e->getMessage());
        $this->assertStringNotContainsString('tokens/saldo', $e->getMessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertNotEmpty($debugmessages);
        foreach ($debugmessages as $debug) {
            $this->assertStringNotContainsString('secret-body', $debug->message);
        }
        $this->resetDebugging();
    }

    /**
     * An invalid JSON response is reported with the decoder message only: the body never leaks.
     */
    public function test_invalid_json_hides_body(): void {
        $this->resetAfterTest();
        $this->seed_instance(['licensekey' => 'test-key']);

        $curl = new fake_curl();
        $curl->enqueue('<html>secret-body</html>', 200);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('jsondecodeerror', $e->errorcode);
        $this->assertSame('aiprovider_datacurso', $e->module);
        $this->assertStringNotContainsString('secret-body', $e->getMessage());
        $this->assertStringNotContainsString('shop.datacurso.com', $e->getMessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertNotEmpty($debugmessages);
        foreach ($debugmessages as $debug) {
            $this->assertStringNotContainsString('secret-body', $debug->message);
        }
        $this->resetDebugging();
    }

    /**
     * A valid JSON response is decoded and returned, and the license key travels as a header.
     */
    public function test_get_returns_decoded_json(): void {
        $this->resetAfterTest();
        $this->seed_instance(['licensekey' => 'test-key']);

        $curl = new fake_curl();
        $curl->enqueue(json_encode(['saldo' => 42]), 200);

        $result = $this->make_client($curl)->get('tokens/saldo', ['userid' => 7]);

        $this->assertSame(['saldo' => 42], $result);
        $this->assertCount(1, $curl->calls);
        $this->assertSame('GET', $curl->calls[0]['method']);
        // The moodle_url class percent-encodes the endpoint inside the api= query value.
        $this->assertStringContainsString('tokens/saldo', urldecode($curl->calls[0]['url']));
        $this->assertStringContainsString('userid=7', $curl->calls[0]['url']);
        $this->assertContains('License-Key: test-key', $curl->calls[0]['options']['CURLOPT_HTTPHEADER']);
    }
}
