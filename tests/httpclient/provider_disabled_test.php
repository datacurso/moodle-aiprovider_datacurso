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

/**
 * Tests that the AI clients respect the "Enabled" switch of the provider.
 *
 * With the provider disabled in Site administration > AI > AI providers, no AI request leaves the
 * site, while the shop client (balance, consumption history) keeps working.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 * @covers     \aiprovider_datacurso\httpclient\ai_services_api
 * @covers     \aiprovider_datacurso\httpclient\ai_course_api
 */
final class provider_disabled_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('licensekey', 'test-key', 'aiprovider_datacurso');
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
    }

    /**
     * Build an AI services client whose transport is the given fake, counting the transports created.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_services_api
     */
    private function make_services_client(fake_curl $curl): ai_services_api {
        return new class ($curl) extends ai_services_api {
            /** @var int Number of transports created. */
            public int $curlscreated = 0;

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
                $this->curlscreated++;
                return $this->fakecurl;
            }
        };
    }

    /**
     * Build a course AI client whose transport is the given fake, counting the transports created.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_course_api
     */
    private function make_course_client(fake_curl $curl): ai_course_api {
        return new class ($curl) extends ai_course_api {
            /** @var int Number of transports created. */
            public int $curlscreated = 0;

            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct(null, 'https://course.example.invalid', 'https://eu.course.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                $this->curlscreated++;
                return $this->fakecurl;
            }
        };
    }

    /**
     * Assert that the callback is refused with the provider disabled message.
     *
     * @param callable $callback Code that makes an AI request.
     */
    private function assert_refused_as_disabled(callable $callback): void {
        try {
            $callback();
            $this->fail('Expected the providerdisabled exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('providerdisabled', $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
            $this->assertSame(get_string('providerdisabled', 'aiprovider_datacurso'), $e->getMessage());
        }
    }

    /**
     * I1: with the provider disabled, the AI services client refuses the request without opening a connection.
     */
    public function test_disabled_provider_refuses_ai_services_requests_without_a_connection(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = $this->make_services_client($curl);

        $this->assert_refused_as_disabled(fn() => $client->request('POST', '/jedomi/v1/chat/completions', [
            'model' => 'any',
            'messages' => [],
        ]));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I1: with the provider disabled, the course AI client refuses requests and downloads without a connection.
     */
    public function test_disabled_provider_refuses_course_requests_and_downloads(): void {
        $this->setAdminUser();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = $this->make_course_client($curl);

        $this->assert_refused_as_disabled(fn() => $client->request('POST', '/course/execute', ['prompt' => 'hello']));
        $this->assert_refused_as_disabled(fn() => $client->download_file('/resources/package.zip', 'package.zip'));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I1: with the provider disabled, an upload is refused before reaching the service.
     */
    public function test_disabled_provider_refuses_uploads(): void {
        $this->setAdminUser();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $curl = new fake_curl();
        $client = $this->make_course_client($curl);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'aiprovider_datacurso',
            'filearea' => 'test',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'content');

        $this->assert_refused_as_disabled(fn() => $client->upload_file('/upload-file', $file));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I2: with the provider enabled, the AI clients send their requests as before.
     */
    public function test_enabled_provider_sends_requests(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        $curl = new fake_curl();
        $curl->enqueue('{"answer": "services"}');
        $curl->enqueue('{"answer": "course"}');

        $services = $this->make_services_client($curl);
        $course = $this->make_course_client($curl);

        $this->assertSame(['answer' => 'services'], $services->request('POST', '/jedomi/v1/chat/completions', []));
        $this->assertSame(['answer' => 'course'], $course->request('POST', '/course/execute', []));
        $this->assertCount(2, $curl->calls);
        $this->assertSame('https://ai.example.invalid/jedomi/v1/chat/completions', $curl->calls[0]['url']);
        $this->assertSame('https://course.example.invalid/course/execute', $curl->calls[1]['url']);
    }

    /**
     * I3: the shop client keeps working with the provider disabled, so balance and history still load.
     */
    public function test_shop_client_works_with_the_provider_disabled(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $curl = new fake_curl();
        $curl->enqueue('{"saldo": 10}');
        $shop = new class ($curl) extends datacurso_api {
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

        $this->assertSame(['saldo' => 10], $shop->get('tokens/saldo'));
        $this->assertCount(1, $curl->calls);
    }
}
