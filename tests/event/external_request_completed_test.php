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

namespace aiprovider_datacurso\event;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../fixtures/fake_curl.php');

use aiprovider_datacurso\httpclient\ai_course_api;
use aiprovider_datacurso\httpclient\ai_services_api;
use aiprovider_datacurso\httpclient\datacurso_api;
use aiprovider_datacurso\httpclient\fake_curl;
use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\outbound_privacy;
use aiprovider_datacurso\process_generate_image;
use aiprovider_datacurso\process_generate_text;
use aiprovider_datacurso\provider;
use core_ai\aiactions\generate_image;
use core_ai\aiactions\generate_text;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the audit event logged for every request the plugin sends to an external Datacurso service.
 *
 * AIP-SEC-005-R1: one event per external operation, with the outcome and no personal data.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\event\external_request_completed
 * @covers     \aiprovider_datacurso\local\request_audit
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api
 * @covers     \aiprovider_datacurso\abstract_processor
 */
final class external_request_completed_test extends \advanced_testcase {
    /** @var string License key configured for the tests. */
    private const LICENSE_KEY = 'audit-license-key-123';

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        set_config('licensekey', self::LICENSE_KEY, 'aiprovider_datacurso');
        // Resolve the region beforehand, so building a client does not ask the shop.
        set_config(license_region::REGION, '0', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
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
     * Build a course AI client whose transport is the given fake and whose downloads return the given file.
     *
     * @param fake_curl $curl Transport double.
     * @param \stored_file|null $downloaded File handed back by a download, instead of fetching it.
     * @return ai_course_api
     */
    private function make_course_client(fake_curl $curl, ?\stored_file $downloaded = null): ai_course_api {
        return new class ($curl, $downloaded) extends ai_course_api {
            /** @var string|null URL a download was fetched from. */
            public ?string $downloadurl = null;

            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             * @param \stored_file|null $downloaded File handed back by a download.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl,
                /** @var \stored_file|null File handed back by a download. */
                private ?\stored_file $downloaded
            ) {
                parent::__construct(null, 'https://course.example.invalid', 'https://eu.course.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }

            #[\Override]
            protected function fetch_file_from_url(array $fileinfo, string $url, array $options): ?\stored_file {
                $this->downloadurl = $url;
                return $this->downloaded;
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
     * Install a Guzzle mock client for the core AI processors.
     *
     * @param array $responses Queue of Response/exception objects.
     */
    private function set_mock_http(array $responses): void {
        $stack = HandlerStack::create(new MockHandler($responses));
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));
    }

    /**
     * Build a stored file, as a caller of upload_file() holds.
     *
     * @param string $filename File name.
     * @return \stored_file
     */
    private function make_stored_file(string $filename = 'secret-syllabus.txt'): \stored_file {
        return get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'aiprovider_datacurso',
            'filearea' => 'test',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
        ], 'file content');
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
     * Assert that the serialised event carries none of the given secrets.
     *
     * @param external_request_completed $event Event to inspect.
     * @param string[] $secrets Values that must never be logged.
     */
    private function assert_event_omits(external_request_completed $event, array $secrets): void {
        $serialised = json_encode($event->get_data()) . $event->get_description();
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $serialised);
        }
    }

    /**
     * A successful POST logs exactly one success event, in the system context, with the path only.
     */
    public function test_post_success_logs_one_event_without_query_string(): void {
        global $CFG;
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $curl = new fake_curl();
        $curl->enqueue('{"reply": "answer-body-text"}');
        $sink = $this->redirectEvents();

        $this->make_services_client($curl)->request('POST', '/forum/reply?token=abc&debug=1', [
            'prompt' => 'secret prompt text',
        ]);

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame(\context_system::instance()->id, $event->contextid);
        $this->assertSame((int) $user->id, (int) $event->userid);
        $this->assertNull($event->relateduserid);
        $this->assertSame('r', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_OTHER, $event->edulevel);
        $this->assertSame([
            'client' => 'ai_services',
            'service' => 'local_forum_ai',
            'method' => 'POST',
            'endpoint' => '/forum/reply',
            'result' => 'success',
            'httpstatus' => 200,
        ], $event->other);
        $this->assert_event_omits($event, [
            self::LICENSE_KEY,
            'secret prompt text',
            'answer-body-text',
            $CFG->wwwroot,
            outbound_privacy::pseudonymise_userid($user->id),
            'token=abc',
        ]);
    }

    /**
     * An upload logs one success event without the file name.
     */
    public function test_upload_success_logs_one_event_without_file_name(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"status": "ok"}');
        $sink = $this->redirectEvents();

        $this->make_course_client($curl)->upload_file('/course/sillabus/upload', $this->make_stored_file());

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('ai_course', $events[0]->other['client']);
        $this->assertSame('local_coursegen', $events[0]->other['service']);
        $this->assertSame('UPLOAD', $events[0]->other['method']);
        $this->assertSame('/course/sillabus/upload', $events[0]->other['endpoint']);
        $this->assertSame('success', $events[0]->other['result']);
        $this->assert_event_omits($events[0], ['secret-syllabus', self::LICENSE_KEY]);
    }

    /**
     * A shop GET logs one success event naming the API endpoint without its parameters.
     */
    public function test_shop_get_logs_one_event(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"saldo": 10}');
        $sink = $this->redirectEvents();

        $this->make_shop_client($curl)->get('tokens/saldo', ['userid' => 'abc']);

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame([
            'client' => 'shop',
            'service' => 'aiprovider_datacurso',
            'method' => 'GET',
            'endpoint' => 'tokens/saldo',
            'result' => 'success',
            'httpstatus' => 200,
        ], $events[0]->other);
        $this->assert_event_omits($events[0], [self::LICENSE_KEY]);
    }

    /**
     * A shop transport failure logs one failure event with the error code and no transport text.
     */
    public function test_shop_curl_error_logs_failure(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('Could not resolve host: shop.example.invalid', 0, 'Could not resolve host: shop.example.invalid', 6);
        $sink = $this->redirectEvents();

        $this->expect_exception(fn() => $this->make_shop_client($curl)->post('tokens/consume', ['credits' => 1]));

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame('curlerror', $events[0]->other['errorcode']);
        $this->assertSame(0, $events[0]->other['httpstatus']);
        $this->assert_event_omits($events[0], ['Could not resolve host']);
    }

    /**
     * A download logs one success event in the context the consumer plugin set.
     */
    public function test_download_logs_one_event_in_the_audit_context(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $client = $this->make_course_client(new fake_curl(), $this->make_stored_file('package.zip'));
        $client->set_audit_context($coursecontext);
        $sink = $this->redirectEvents();

        $file = $client->download_file('/resources/package?session=xyz', 'secret-package.zip');

        $this->assertInstanceOf(\stored_file::class, $file);
        $this->assertSame('https://course.example.invalid/resources/package?session=xyz', $client->downloadurl);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame($coursecontext->id, $events[0]->contextid);
        $this->assertSame('GET', $events[0]->other['method']);
        $this->assertSame('/resources/package', $events[0]->other['endpoint']);
        $this->assertSame('success', $events[0]->other['result']);
        $this->assert_event_omits($events[0], ['secret-package', self::LICENSE_KEY]);
    }

    /**
     * A failing download logs one failure event and the exception reaches the caller.
     */
    public function test_failed_download_logs_failure(): void {
        $this->setAdminUser();
        $client = new class (new fake_curl()) extends ai_course_api {
            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Unused transport double.
             */
            public function __construct(fake_curl $fakecurl) {
                parent::__construct(null, 'https://course.example.invalid', 'https://eu.course.example.invalid');
            }

            #[\Override]
            protected function fetch_file_from_url(array $fileinfo, string $url, array $options): ?\stored_file {
                throw new \file_exception('storedfileproblem', 'Cannot fetch file from URL');
            }
        };
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(fn() => $client->download_file('/resources/package', 'package.zip'));

        $this->assertSame('storedfileproblem', $e->errorcode);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame('storedfileproblem', $events[0]->other['errorcode']);
    }

    /**
     * A request on behalf of another user names that user as the related user, never the pseudonym.
     */
    public function test_request_for_another_user_sets_related_user_and_context(): void {
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $curl->enqueue('{"ok": true}');
        $client = $this->make_services_client($curl);
        $sink = $this->redirectEvents();

        $client->request('POST', '/assign/review', ['userid' => (int) $student->id]);
        $client->set_audit_context($coursecontext);
        $client->request('POST', '/assign/review', ['userid' => outbound_privacy::pseudonymise_userid($student->id)]);

        $events = $this->audit_events($sink);
        $this->assertCount(2, $events);
        $this->assertSame((int) $student->id, (int) $events[0]->relateduserid);
        $this->assertSame(\context_system::instance()->id, $events[0]->contextid);
        // A pseudonym is never reversed to find the related user.
        $this->assertNull($events[1]->relateduserid);
        $this->assertSame($coursecontext->id, $events[1]->contextid);
        $this->assert_event_omits($events[1], [outbound_privacy::pseudonymise_userid($student->id)]);
    }

    /**
     * A 403 over the rate limit logs one failure event with the status and the error code.
     */
    public function test_rate_limit_rejection_logs_failure_with_status_and_errorcode(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"detail": "rate_limit_exceeded", "reset_at": 0}', 403);
        $sink = $this->redirectEvents();

        $e = $this->expect_exception(fn() => $this->make_services_client($curl)->request('POST', '/forum/reply', []));

        $this->assertSame('error_ratelimit_exceeded', $e->errorcode);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame(403, $events[0]->other['httpstatus']);
        $this->assertSame('error_ratelimit_exceeded', $events[0]->other['errorcode']);
    }

    /**
     * A transport failure logs one failure event with the cURL error code and no transport text.
     */
    public function test_curl_error_logs_failure_without_transport_text(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $errortext = 'Could not resolve host: ai.example.invalid';
        $curl->enqueue($errortext, 0, $errortext, 6);
        $sink = $this->redirectEvents();

        $this->expect_exception(fn() => $this->make_services_client($curl)->request('POST', '/forum/reply', []));

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame('curlerror', $events[0]->other['errorcode']);
        $this->assertSame(0, $events[0]->other['httpstatus']);
        $this->assert_event_omits($events[0], [$errortext]);
    }

    /**
     * With the provider disabled the request is logged as refused and no connection is opened.
     */
    public function test_disabled_provider_logs_refused_without_a_connection(): void {
        $this->setAdminUser();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $curl = new fake_curl();
        $sink = $this->redirectEvents();

        $this->expect_exception(fn() => $this->make_services_client($curl)->request('POST', '/forum/reply', []));

        $this->assertSame([], $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('providerdisabled', $events[0]->other['errorcode']);
        $this->assertSame(0, $events[0]->other['httpstatus']);
    }

    /**
     * Without a license key the request is logged as refused and no connection is opened.
     */
    public function test_missing_license_logs_refused_without_a_connection(): void {
        $this->setAdminUser();
        unset_config('licensekey', 'aiprovider_datacurso');
        // Keep the region resolved for the new (empty) license, so building the client does not ask the shop.
        set_config(license_region::FINGERPRINT, license_region::fingerprint(), 'aiprovider_datacurso');
        $curl = new fake_curl();
        $sink = $this->redirectEvents();

        $this->expect_exception(fn() => $this->make_services_client($curl)->request('POST', '/forum/reply', []));

        $this->assertSame([], $curl->calls);
        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame('refused', $events[0]->other['result']);
        $this->assertSame('invalidlicensekey', $events[0]->other['errorcode']);
    }

    /**
     * A core AI action logs one event in the action context, naming the user it was made for.
     */
    public function test_core_ai_action_logs_one_event_in_the_action_context(): void {
        global $CFG;
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $this->set_mock_http([new Response(200, [], json_encode([
            'choices' => [['message' => ['content' => 'generated-answer-text'], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1, 'total_tokens' => 2],
        ]))]);
        $action = new generate_text($coursecontext->id, (int) $student->id, 'secret prompt text');
        $processor = new class (new provider(), $action) extends process_generate_text {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/chat/completions?x=1');
            }
        };
        $sink = $this->redirectEvents();

        $processor->process();

        $events = $this->audit_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame($coursecontext->id, $events[0]->contextid);
        $this->assertSame((int) $student->id, (int) $events[0]->relateduserid);
        $this->assertSame([
            'client' => 'core_ai',
            'service' => 'aiprovider_datacurso',
            'method' => 'POST',
            'endpoint' => '/provider/chat/completions',
            'result' => 'success',
            'httpstatus' => 200,
        ], $events[0]->other);
        $this->assert_event_omits($events[0], [
            'secret prompt text',
            'generated-answer-text',
            self::LICENSE_KEY,
            $CFG->wwwroot,
            outbound_privacy::pseudonymise_userid($student->id),
        ]);
    }

    /**
     * Core AI rejections and transport errors are logged as failures with their codes.
     */
    public function test_core_ai_failures_are_logged(): void {
        $this->setAdminUser();
        $this->set_mock_http([
            new Response(403, [], json_encode(['detail' => 'tokens_not_sufficient'])),
            new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://example.invalid')),
        ]);
        $action = new generate_text(\context_system::instance()->id, 0, 'hello');
        $make = fn() => new class (new provider(), $action) extends process_generate_text {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/chat/completions');
            }
        };
        $sink = $this->redirectEvents();

        $make()->process();
        $make()->process();
        $this->resetDebugging();

        $events = $this->audit_events($sink);
        $this->assertCount(2, $events);
        $this->assertSame('failure', $events[0]->other['result']);
        $this->assertSame(403, $events[0]->other['httpstatus']);
        $this->assertSame('notenoughtokens', $events[0]->other['errorcode']);
        $this->assertSame('failure', $events[1]->other['result']);
        $this->assertSame(0, $events[1]->other['httpstatus']);
        $this->assertSame('ConnectException', $events[1]->other['errorcode']);
        $this->assert_event_omits($events[1], ['Could not resolve host']);
    }

    /**
     * The image fetch of the image action is logged as its own request, without the image file name.
     */
    public function test_core_ai_image_fetch_is_logged_without_file_name(): void {
        $this->setAdminUser();
        $image = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        $this->set_mock_http([
            new Response(200, [], json_encode(['data' => [['url' => 'https://example.invalid/images/secret-name.png?sig=1']]])),
            new Response(200, [], $png),
        ]);
        $action = new generate_image(\context_system::instance()->id, 2, 'A robot', 'hd', 'square', 1, 'vivid');
        $processor = new class (new provider(), $action) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/images/generations');
            }
        };
        $sink = $this->redirectEvents();

        $processor->process();
        $this->resetDebugging();

        $events = $this->audit_events($sink);
        $this->assertCount(2, $events);
        $this->assertSame('/provider/images/generations', $events[0]->other['endpoint']);
        $this->assertSame('GET', $events[1]->other['method']);
        $this->assertSame('/images/', $events[1]->other['endpoint']);
        $this->assertSame('success', $events[1]->other['result']);
        $this->assert_event_omits($events[1], ['secret-name', 'sig=1']);
    }

    /**
     * A failure while logging is reported to developers and never breaks the request.
     */
    public function test_logging_failure_does_not_break_the_request(): void {
        $this->setAdminUser();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
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
                parent::__construct(null, 'https://ai.example.invalid', 'https://eu.ai.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }

            #[\Override]
            protected function get_client_name(): string {
                // Not an allowed value: the event refuses to be created.
                return 'unknown_client';
            }
        };
        $sink = $this->redirectEvents();

        $this->assertSame(['ok' => true], $client->request('POST', '/forum/reply', []));

        $this->assertSame([], $this->audit_events($sink));
        $this->assertDebuggingCalled();
    }

    /**
     * Event data accepted and refused by the validation.
     *
     * @return array
     */
    public static function invalid_other_provider(): array {
        $valid = [
            'client' => 'ai_services',
            'service' => 'local_forum_ai',
            'method' => 'POST',
            'endpoint' => '/forum/reply',
            'result' => 'success',
            'httpstatus' => 200,
        ];
        return [
            'unknown key' => [$valid + ['licensekey' => 'x']],
            'payload key' => [$valid + ['payload' => 'x']],
            'missing endpoint' => [array_diff_key($valid, ['endpoint' => 0])],
            'failure without errorcode' => [array_merge($valid, ['result' => 'failure'])],
            'refused without errorcode' => [array_merge($valid, ['result' => 'refused'])],
            'unknown result' => [array_merge($valid, ['result' => 'maybe'])],
            'unknown client' => [array_merge($valid, ['client' => 'elsewhere'])],
            'endpoint with query string' => [array_merge($valid, ['endpoint' => '/forum/reply?x=1'])],
            'status not an integer' => [array_merge($valid, ['httpstatus' => '200'])],
        ];
    }

    /**
     * The event refuses unknown keys and incomplete outcomes.
     *
     * @dataProvider invalid_other_provider
     * @param array $other Data of the event.
     */
    public function test_validation_refuses_invalid_data(array $other): void {
        $this->expectException(\coding_exception::class);
        external_request_completed::create([
            'context' => \context_system::instance(),
            'other' => $other,
        ]);
    }

    /**
     * A failure with its error code is accepted and described without personal data.
     */
    public function test_failure_with_errorcode_is_accepted(): void {
        $event = external_request_completed::create([
            'context' => \context_system::instance(),
            'other' => [
                'client' => 'shop',
                'service' => 'aiprovider_datacurso',
                'method' => 'GET',
                'endpoint' => 'tokens/saldo',
                'result' => 'failure',
                'httpstatus' => 500,
                'errorcode' => 'httperror',
            ],
        ]);

        $this->assertSame(get_string('event_external_request_completed', 'aiprovider_datacurso'), $event->get_name());
        $this->assertStringContainsString('tokens/saldo', $event->get_description());
        $this->assertStringContainsString('failure', $event->get_description());
    }
}
