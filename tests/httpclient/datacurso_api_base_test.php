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

require_once(__DIR__ . '/../fixtures/test_upload_client.php');
require_once(__DIR__ . '/../fixtures/fake_curl.php');

/**
 * Tests for the Datacurso API HTTP client: file upload and error reporting.
 *
 * The upload contract is shared with local_coursegen, which holds Moodle
 * stored_file objects and has no path on disk to give. That contract was lost
 * once in a branch merge, so these tests pin both the signature and the
 * behaviour that depends on it.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 */
final class datacurso_api_base_test extends \advanced_testcase {
    /**
     * Build a client whose transport is the given fake cURL wrapper.
     *
     * @param fake_curl $curl
     * @return datacurso_api_base
     */
    private function make_client(fake_curl $curl): datacurso_api_base {
        return new class ('https://example.invalid', 'test-key', $curl) extends datacurso_api_base {
            /**
             * Constructor.
             *
             * @param string $baseurl Base URL.
             * @param string $licensekey License key.
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                string $baseurl,
                string $licensekey,
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct($baseurl, $licensekey);
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
     * @param datacurso_api_base $client
     * @return \moodle_exception
     */
    private function get_expecting_exception(datacurso_api_base $client): \moodle_exception {
        try {
            $client->request('GET', '/status');
        } catch (\moodle_exception $e) {
            return $e;
        }
        $this->fail('Expected a moodle_exception.');
    }

    /**
     * An HTTP error is logged without the response body, and reported with the status code only.
     */
    public function test_http_error_debug_output_omits_response_body(): void {
        $this->resetAfterTest();

        $curl = new fake_curl();
        $curl->enqueue('secret-body', 500);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('httperror', $e->errorcode);
        $this->assertSame(500, $e->a);
        $this->assertStringNotContainsString('secret-body', $e->getMessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertNotEmpty($debugmessages);
        foreach ($debugmessages as $debug) {
            $this->assertStringNotContainsString('secret-body', $debug->message);
        }
        $this->resetDebugging();
    }

    /**
     * An invalid JSON response is logged without the response body.
     */
    public function test_invalid_json_debug_output_omits_response_body(): void {
        $this->resetAfterTest();

        $curl = new fake_curl();
        $curl->enqueue('<html>secret-body</html>', 200);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('jsondecodeerror', $e->errorcode);
        $this->assertStringNotContainsString('secret-body', $e->getMessage());

        $debugmessages = $this->getDebuggingMessages();
        $this->assertNotEmpty($debugmessages);
        foreach ($debugmessages as $debug) {
            $this->assertStringNotContainsString('secret-body', $debug->message);
        }
        $this->resetDebugging();
    }

    /**
     * A transport failure is reported with the cURL error number only: the raw libcurl text
     * (which may contain hostnames) is confined to developer debugging output.
     */
    public function test_curl_failure_hides_transport_details(): void {
        $this->resetAfterTest();
        set_config('licensekey', 'KEY', 'aiprovider_datacurso');

        $curl = new fake_curl();
        // Moodle's curl wrapper hands back the error text as the response body on transport failure.
        $errortext = 'Could not resolve host: course-ai-v2.datacurso.com';
        $curl->enqueue($errortext, 0, $errortext, 6);

        try {
            $this->make_client($curl)->request('POST', '/course/execute', ['prompt' => 'hello']);
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('curlerror', $e->errorcode);
            $this->assertSame(6, $e->a);
            $this->assertSame(get_string('curlerror', 'aiprovider_datacurso', 6), $e->getMessage());
            $this->assertStringNotContainsString('course-ai-v2', $e->getMessage());
        }

        $debugmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalledCount(1);
        $this->assertStringContainsString('6', $debugmessages[0]->message);
    }

    /**
     * Build a real stored_file in the draft area, as a caller would hold.
     *
     * @param string $content File content.
     * @param string $filename File name.
     * @param string $mimetype MIME type to record on the file.
     * @return \stored_file
     */
    private function make_stored_file(
        string $content = 'syllabus content',
        string $filename = 'syllabus.pdf',
        string $mimetype = 'application/pdf'
    ): \stored_file {
        $record = [
            'contextid' => \context_system::instance()->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];

        return get_file_storage()->create_file_from_string($record, $content);
    }

    /**
     * The second parameter must be a stored_file, which is what callers hold.
     *
     * Declaring it as a string path is what broke syllabus upload: Moodle keeps
     * files in the file storage API, so no caller has a path to pass.
     */
    public function test_upload_file_takes_a_stored_file(): void {
        $param = (new \ReflectionMethod(datacurso_api_base::class, 'upload_file'))->getParameters()[1];

        $this->assertSame('stored_file', $param->getType()->getName());
    }

    /**
     * Extra POST parameters are the third argument, not the fifth.
     *
     * Callers pass three positional arguments. Inserting parameters ahead of
     * this one silently lands the extras in the wrong slot.
     */
    public function test_extra_params_are_the_third_parameter(): void {
        $params = (new \ReflectionMethod(datacurso_api_base::class, 'upload_file'))->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('extraparams', $params[2]->getName());
        $this->assertSame('array', $params[2]->getType()->getName());
    }

    /**
     * A stored_file is uploaded with its own name and MIME type, plus the extras.
     */
    public function test_upload_file_sends_the_file_with_its_name_and_mimetype(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/ai/provider/datacurso/tests/fixtures/test_upload_client.php");

        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $file = $this->make_stored_file('report body', 'informe.pdf', 'application/pdf');

        $client->upload_file('/course/sillabus/upload', $file, ['thread_id' => 'abc123']);

        $this->assertSame('UPLOAD', $client->method);
        $this->assertSame('/course/sillabus/upload', $client->path);
        $this->assertSame('abc123', $client->payload['thread_id']);

        $curlfile = $client->payload['file'];
        $this->assertInstanceOf(\CURLFile::class, $curlfile);
        $this->assertSame('informe.pdf', $curlfile->getPostFilename());
        $this->assertSame('application/pdf', $curlfile->getMimeType());
    }

    /**
     * The temporary copy holds the file content while the request is in flight.
     */
    public function test_the_temporary_copy_holds_the_file_content(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/ai/provider/datacurso/tests/fixtures/test_upload_client.php");

        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $file = $this->make_stored_file('exact bytes to upload');

        $client->upload_file('/course/sillabus/upload', $file);

        $this->assertSame('exact bytes to upload', $client->contentatrequest);
    }

    /**
     * The temporary copy is removed once the request completes.
     */
    public function test_the_temporary_copy_is_removed_afterwards(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/ai/provider/datacurso/tests/fixtures/test_upload_client.php");

        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');

        $client->upload_file('/course/sillabus/upload', $this->make_stored_file());

        $this->assertFileDoesNotExist($client->payload['file']->getFilename());
    }

    /**
     * The temporary copy is removed even when the request throws.
     *
     * Without the finally block a failed upload leaves the file behind, and a
     * syllabus is not small.
     */
    public function test_the_temporary_copy_is_removed_when_the_request_fails(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/ai/provider/datacurso/tests/fixtures/test_upload_client.php");

        $this->resetAfterTest();

        $client = new test_upload_client('https://example.invalid');
        $client->failwith = new \moodle_exception('error');

        try {
            $client->upload_file('/course/sillabus/upload', $this->make_stored_file());
            $this->fail('Expected the request to throw.');
        } catch (\moodle_exception $e) {
            $this->assertFileDoesNotExist($client->temppath);
        }
    }

    /**
     * The license header carries the configured key.
     */
    public function test_license_header_carries_the_configured_key(): void {
        $client = $this->make_client(new fake_curl());

        $this->assertSame('License-Key: test-key', $client->get_license_header());
    }

    /**
     * Spaces around the key are not sent.
     */
    public function test_license_header_trims_the_key(): void {
        $client = new datacurso_api_base('https://example.invalid', "  spaced-key \n");

        $this->assertSame('License-Key: spaced-key', $client->get_license_header());
    }

    /**
     * Provide the keys that must not be sent.
     *
     * @return array<string, array{0: ?string}>
     */
    public static function missing_license_key_provider(): array {
        return [
            'empty string' => [''],
            'only spaces' => ['   '],
            'only a line break' => ["\n"],
        ];
    }

    /**
     * Without a license key there is no header: the call fails with the localized error.
     *
     * @dataProvider missing_license_key_provider
     * @param string|null $licensekey Key given to the client.
     */
    public function test_license_header_fails_without_a_key(?string $licensekey): void {
        $this->resetAfterTest();
        $client = new datacurso_api_base('https://example.invalid', $licensekey);
        $expectedmessage = get_string('invalidlicensekey', 'aiprovider_datacurso');

        try {
            $client->get_license_header();
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidlicensekey', $e->errorcode);
            $this->assertSame($expectedmessage, $e->getMessage());
        }
        $this->resetDebugging();
    }

    /**
     * A key that was never configured fails the same way when the client reads it from the settings.
     */
    public function test_license_header_fails_when_the_setting_is_empty(): void {
        $this->resetAfterTest();
        set_config('licensekey', '', 'aiprovider_datacurso');
        $client = new datacurso_api_base('https://example.invalid');
        $expectedmessage = get_string('invalidlicensekey', 'aiprovider_datacurso');

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage($expectedmessage);
        $client->get_license_header();
    }

    /**
     * Every request carries the license header built by the shared method.
     */
    public function test_request_sends_the_license_header(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');

        $this->make_client($curl)->request('GET', '/status');

        $this->assertCount(1, $curl->calls);
        $this->assertContains('License-Key: test-key', $curl->calls[0]['options']['CURLOPT_HTTPHEADER']);
    }

    /**
     * Every request tells the service which plugin is calling and its release.
     */
    public function test_request_identifies_the_plugin_and_its_release(): void {
        $this->resetAfterTest();
        $release = \core_plugin_manager::instance()->get_plugin_info('aiprovider_datacurso')->release;
        $this->assertNotEmpty($release);

        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $this->make_client($curl)->request('GET', '/status');

        $headers = $curl->calls[0]['options']['CURLOPT_HTTPHEADER'];
        $this->assertContains('X-Plugin-Name: aiprovider_datacurso', $headers);
        $this->assertContains('X-Plugin-Version: ' . $release, $headers);
    }

    /**
     * A plugin that uses the client names itself, and the client keeps that name.
     */
    public function test_the_calling_plugin_can_name_itself(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = $this->make_client($curl);

        $this->assertSame($client, $client->set_caller_component('local_notinstalled'));
        $client->request('GET', '/status');

        $headers = $curl->calls[0]['options']['CURLOPT_HTTPHEADER'];
        $this->assertContains('X-Plugin-Name: local_notinstalled', $headers);
        $this->assertNotContains('X-Plugin-Name: aiprovider_datacurso', $headers);
    }

    /**
     * Without a release to report, only the name is sent and the service asks for an update.
     */
    public function test_a_component_without_a_release_sends_no_version(): void {
        $this->resetAfterTest();
        $client = $this->make_client(new fake_curl())->set_caller_component('local_notinstalled');

        $this->assertSame(['X-Plugin-Name: local_notinstalled'], $client->get_plugin_headers());
    }

    /**
     * The service refusing an old release is shown with the plugin, its version and the minimum.
     */
    public function test_an_outdated_plugin_gets_a_message_with_the_minimum_version(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue(json_encode(['detail' => [
            'string_id' => 'plugin_update_required',
            'string' => 'English text from the service',
            'string_args' => ['plugin' => 'local_coursegen', 'version' => '1.0.1', 'minimum' => '1.2.0'],
        ]]), 426);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('plugin_update_required', $e->errorcode);
        $this->assertStringContainsString('local_coursegen 1.0.1', $e->getMessage());
        $this->assertStringContainsString('1.2.0', $e->getMessage());
        $this->assertDebuggingCalled();
    }

    /**
     * Values that come from the service are shown as plain text.
     */
    public function test_the_values_of_the_update_message_are_plain_text(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue(json_encode(['detail' => [
            'string_id' => 'plugin_update_required',
            'string_args' => ['plugin' => '<script>alert(1)</script>local_x', 'version' => str_repeat('9', 200), 'minimum' => '1.2'],
        ]]), 426);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertStringNotContainsString('<script>', $e->getMessage());
        $this->assertLessThan(400, strlen($e->getMessage()));
        $this->assertDebuggingCalled();
    }

    /**
     * A plugin that reports no version is told to update to the latest one.
     *
     * @dataProvider unreadable_upgrade_answers
     * @param string $body Body of the 426 answer.
     */
    public function test_a_plugin_that_reports_no_version_is_asked_to_update(string $body): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue($body, 426);

        $e = $this->get_expecting_exception($this->make_client($curl));

        $this->assertSame('plugin_version_required', $e->errorcode);
        $this->assertDebuggingCalled();
    }

    /**
     * Answers of the service that ask for the version, and bodies that cannot be read.
     *
     * @return array
     */
    public static function unreadable_upgrade_answers(): array {
        return [
            'version required' => [json_encode(['detail' => ['string_id' => 'plugin_version_required', 'string' => 'x']])],
            'not json' => ['<html>Upgrade Required</html>'],
            'detail is text' => [json_encode(['detail' => 'upgrade'])],
        ];
    }

    /**
     * A request without a license key stops before any network call.
     */
    public function test_request_without_a_key_never_reaches_the_service(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $client = new class ('https://example.invalid', '', $curl) extends datacurso_api_base {
            /**
             * Constructor.
             *
             * @param string $baseurl Base URL.
             * @param string $licensekey License key.
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                string $baseurl,
                string $licensekey,
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct($baseurl, $licensekey);
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };

        try {
            $client->request('GET', '/status');
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidlicensekey', $e->errorcode);
        }

        $this->assertSame([], $curl->calls);
        $this->resetDebugging();
    }

    /**
     * A download without a license key stops before any file is created or requested.
     */
    public function test_download_without_a_key_fails_before_creating_a_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $client = new datacurso_api_base('https://example.invalid', '');

        try {
            $client->download_file('/files/download?path=x', 'x.pdf');
            $this->fail('Expected a moodle_exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidlicensekey', $e->errorcode);
        }

        $admin = get_admin();
        $usercontext = \context_user::instance($admin->id);
        $storage = get_file_storage();
        $files = $storage->get_area_files($usercontext->id, 'user', 'draft', false, 'id', false);
        $this->assertCount(0, $files);
        $this->resetDebugging();
    }

    /**
     * Decode the JSON body of the first POST received by the fake transport.
     *
     * @param fake_curl $curl
     * @return array
     */
    private function posted_body(fake_curl $curl): array {
        $this->assertSame('POST', $curl->calls[0]['method']);
        return json_decode((string) $curl->calls[0]['params'], true);
    }

    /**
     * The outbound userid is the site-scoped pseudonym of the current user, never the raw id,
     * while the site and locale fields the services rely on are sent exactly as before.
     *
     * AIP-PRIV-001 (partial): pseudonymous user identifier.
     */
    public function test_post_sends_pseudonymous_userid_and_keeps_site_fields(): void {
        global $CFG;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $curl = new fake_curl();
        $curl->enqueue(json_encode(['ok' => true]), 200);
        $this->make_client($curl)->request('POST', '/course/execute', ['prompt' => 'hello']);

        $body = $this->posted_body($curl);
        $expected = \aiprovider_datacurso\local\outbound_privacy::pseudonymise_userid($user->id);
        $this->assertSame($expected, $body['userid']);
        $this->assertNotSame((string) $user->id, $body['userid']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $body['userid']);

        // The HMAC secret behind the pseudonym stays on the site: not in the body, nor in the headers,
        // and it is not the site UUID the services receive as site_id.
        $secret = \aiprovider_datacurso\local\outbound_privacy::get_pseudonym_secret();
        $this->assertStringNotContainsString($secret, (string) $curl->calls[0]['params']);
        $this->assertStringNotContainsString($secret, implode("\n", $curl->calls[0]['options']['CURLOPT_HTTPHEADER']));
        $this->assertNotSame($secret, $body['site_id']);

        $this->assertSame(datacurso_api_base::get_site_uuid(), $body['site_id']);
        $this->assertSame($CFG->wwwroot, $body['site_url']);
        $this->assertSame(\core_date::get_user_timezone(), $body['timezone']);
        $this->assertSame(current_language(), $body['lang']);
        $this->assertSame('hello', $body['prompt']);
    }

    /**
     * A caller that passes a raw numeric userid gets it pseudonymised too.
     */
    public function test_post_pseudonymises_caller_provided_numeric_userid(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $curl = new fake_curl();
        $curl->enqueue(json_encode(['ok' => true]), 200);
        $this->make_client($curl)->request('POST', '/course/execute', ['userid' => 12345]);

        $body = $this->posted_body($curl);
        $this->assertSame(\aiprovider_datacurso\local\outbound_privacy::pseudonymise_userid(12345), $body['userid']);
        $this->assertNotSame('12345', (string) $body['userid']);
    }

    /**
     * A caller that already pseudonymised the userid (a consumer plugin) keeps its token.
     */
    public function test_post_keeps_caller_provided_pseudonym(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $token = \aiprovider_datacurso\local\outbound_privacy::pseudonymise_userid(777);
        $curl = new fake_curl();
        $curl->enqueue(json_encode(['ok' => true]), 200);
        $this->make_client($curl)->request('POST', '/course/execute', ['userid' => $token]);

        $this->assertSame($token, $this->posted_body($curl)['userid']);
    }
}
