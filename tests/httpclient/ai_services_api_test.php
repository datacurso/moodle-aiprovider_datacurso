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
require_once(__DIR__ . '/../fixtures/resolved_region.php');

use aiprovider_datacurso\resolved_region;

/**
 * Tests for the base URL the AI services client resolves, including the development overrides.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\ai_services_api
 */
final class ai_services_api_test extends \advanced_testcase {
    /** @var string Development URL of the standard region used by the tests. */
    private const DEV_URL = 'https://dev.example.invalid';

    /** @var string Development URL of the European region used by the tests. */
    private const DEV_URL_EU = 'https://dev-eu.example.invalid';

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        set_config('licensekey', 'services-license-key', 'aiprovider_datacurso');
        unset($CFG->aiprovider_datacurso_allow_insecure_transport);
        $this->set_region(false);
    }

    /**
     * Resolve the region of the licence beforehand, so building a client does not ask the shop.
     *
     * @param bool $european Whether the licence belongs to the European deployment.
     */
    private function set_region(bool $european): void {
        resolved_region::keep('services-license-key', $european);
    }

    /**
     * The production URL the client falls back to.
     *
     * @param string $name Name of the constant.
     * @return string Base URL as get_base_url() returns it.
     */
    private function production_url(string $name): string {
        $value = (new \ReflectionClassConstant(ai_services_api::class, $name))->getValue();
        return rtrim($value, '/') . '/';
    }

    /**
     * The production URLs are the committed HTTPS ones, never a developer's local server.
     */
    public function test_production_urls_are_https(): void {
        $this->assertStringStartsWith('https://', $this->production_url('DEFAULT_BASE_URL'));
        $this->assertStringStartsWith('https://', $this->production_url('DEFAULT_BASE_URL_EU'));
    }

    /**
     * Without development settings the production URL is used.
     */
    public function test_production_url_when_no_development_setting(): void {
        $this->assertSame($this->production_url('DEFAULT_BASE_URL'), (new ai_services_api())->get_base_url());
    }

    /**
     * An empty or blank development setting is ignored.
     */
    public function test_blank_development_setting_is_ignored(): void {
        set_config('devservicesurl', '   ', 'aiprovider_datacurso');

        $this->assertSame($this->production_url('DEFAULT_BASE_URL'), (new ai_services_api())->get_base_url());
    }

    /**
     * The development setting replaces the production URL for the standard region.
     */
    public function test_development_setting_overrides_production_url(): void {
        set_config('devservicesurl', self::DEV_URL, 'aiprovider_datacurso');

        $this->assertSame(self::DEV_URL . '/', (new ai_services_api())->get_base_url());
    }

    /**
     * A URL given to the constructor wins over the development setting.
     */
    public function test_constructor_argument_wins_over_development_setting(): void {
        set_config('devservicesurl', self::DEV_URL, 'aiprovider_datacurso');

        $api = new ai_services_api(null, 'https://explicit.example.invalid');

        $this->assertSame('https://explicit.example.invalid/', $api->get_base_url());
    }

    /**
     * The European region uses its own development setting, and the standard region ignores it.
     */
    public function test_european_region_uses_european_development_setting(): void {
        set_config('devservicesurl', self::DEV_URL, 'aiprovider_datacurso');
        set_config('devservicesurleu', self::DEV_URL_EU, 'aiprovider_datacurso');

        $this->assertSame(self::DEV_URL . '/', (new ai_services_api())->get_base_url());

        $this->set_region(true);
        $this->assertSame(self::DEV_URL_EU . '/', (new ai_services_api())->get_base_url());
    }

    /**
     * The European region never falls back to the standard development URL: its data stays on
     * the European production URL unless a European development URL is configured.
     */
    public function test_european_region_does_not_fall_back_to_standard_development_setting(): void {
        $this->set_region(true);
        set_config('devservicesurl', self::DEV_URL, 'aiprovider_datacurso');

        $this->assertSame($this->production_url('DEFAULT_BASE_URL_EU'), (new ai_services_api())->get_base_url());
    }

    /**
     * A European URL given to the constructor wins over the European development setting.
     */
    public function test_european_constructor_argument_wins_over_development_setting(): void {
        $this->set_region(true);
        set_config('devservicesurleu', self::DEV_URL_EU, 'aiprovider_datacurso');

        $api = new ai_services_api(null, null, 'https://explicit-eu.example.invalid');

        $this->assertSame('https://explicit-eu.example.invalid/', $api->get_base_url());
    }

    /**
     * The transport policy still applies to a development URL: plain HTTP is refused before any
     * connection unless config.php allows it.
     */
    public function test_transport_policy_applies_to_development_url(): void {
        global $CFG;
        $this->setAdminUser();
        set_config('devservicesurl', 'http://dev.example.invalid:8002', 'aiprovider_datacurso');

        $curl = new fake_curl();
        try {
            $this->make_client($curl)->request('POST', '/forum/reply', []);
            $this->fail('A plain HTTP development URL must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('insecuretransport', $e->errorcode);
        }
        $this->assertSame([], $curl->calls);
        $this->assertDebuggingCalled();

        $CFG->aiprovider_datacurso_allow_insecure_transport = true;
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $this->assertSame(['ok' => true], $this->make_client($curl)->request('POST', '/forum/reply', []));
        $this->assertCount(1, $curl->calls);
        $this->assertStringStartsWith('http://dev.example.invalid:8002/', $curl->calls[0]['url']);
    }

    /**
     * Build a client that resolves its URL like production code and sends through the given fake.
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
                parent::__construct();
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };
    }
}
