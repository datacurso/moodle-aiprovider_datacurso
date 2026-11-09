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
 * The timezone of the user only reaches the services that use it.
 *
 * AIP-PRIV-001-R1: the AI services read the timezone only on their CV and job matching routes,
 * while the course service uses it on every route.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 */
final class timezone_minimisation_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        set_config('licensekey', 'test-key', 'aiprovider_datacurso');
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
        $this->setUser($this->getDataGenerator()->create_user(['timezone' => 'America/Bogota']));
    }

    /**
     * Build an AI services client whose transport is the given fake.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_services_api
     */
    private function make_services_client(fake_curl $curl): ai_services_api {
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
     * Build a course AI client whose transport is the given fake.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_course_api
     */
    private function make_course_client(fake_curl $curl): ai_course_api {
        return new class ($curl) extends ai_course_api {
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
                return $this->fakecurl;
            }
        };
    }

    /**
     * Decode the JSON body of the first call.
     *
     * @param fake_curl $curl Transport double.
     * @return array
     */
    private function posted_body(fake_curl $curl): array {
        return json_decode((string) $curl->calls[0]['params'], true);
    }

    /**
     * Routes of the AI services that do not read the timezone.
     *
     * @return array
     */
    public static function ai_services_path_provider(): array {
        return [
            'forum' => ['/forum/reply'],
            'assign' => ['/assign/review'],
            'jedomi' => ['/jedomi/v1/chat/completions'],
            'tutor' => ['/chat/message'],
            'prefix only as a substring' => ['/forum/cv/reply'],
        ];
    }

    /**
     * A POST to an AI services route that does not use it carries no timezone, and keeps the other fields.
     *
     * @dataProvider ai_services_path_provider
     * @param string $path Route of the AI services.
     */
    public function test_ai_services_request_omits_timezone(string $path): void {
        global $CFG;
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->make_services_client($curl)->request('POST', $path, ['prompt' => 'hello']);

        $body = $this->posted_body($curl);
        $this->assertArrayNotHasKey('timezone', $body);
        $this->assertSame($CFG->wwwroot, $body['site_url']);
        $this->assertSame(datacurso_api_base::get_site_uuid(), $body['site_id']);
        $this->assertArrayHasKey('userid', $body);
        $this->assertArrayHasKey('lang', $body);
    }

    /**
     * An upload to the AI services carries no timezone either.
     */
    public function test_ai_services_upload_omits_timezone(): void {
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'aiprovider_datacurso',
            'filearea' => 'test',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'content');

        $this->make_services_client($curl)->upload_file('/forum/upload', $file);

        $this->assertArrayNotHasKey('timezone', $curl->calls[0]['params']);
        $this->assertArrayHasKey('site_url', $curl->calls[0]['params']);
    }

    /**
     * Routes of the AI services that read the timezone.
     *
     * @return array
     */
    public static function timezone_path_provider(): array {
        return [
            'cv' => ['/cv/analyse'],
            'job matching' => ['/jobmatch/score'],
        ];
    }

    /**
     * The CV and job matching routes of the AI services keep receiving the timezone.
     *
     * @dataProvider timezone_path_provider
     * @param string $path Route of the AI services.
     */
    public function test_cv_and_jobmatch_routes_keep_timezone(string $path): void {
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->make_services_client($curl)->request('POST', $path, []);

        $this->assertSame('America/Bogota', $this->posted_body($curl)['timezone']);
    }

    /**
     * The course service keeps receiving the timezone.
     */
    public function test_course_client_keeps_timezone(): void {
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->make_course_client($curl)->request('POST', '/course/execute', []);

        $this->assertSame('America/Bogota', $this->posted_body($curl)['timezone']);
    }
}
