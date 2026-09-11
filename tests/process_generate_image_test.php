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

/**
 * Tests for the image generation processor.
 *
 * Ported from the 4.5 security-hardening release (1.5.1): the https-only, host-pinned,
 * extension-whitelisted, no-redirect, size-capped and byte-sniffed image URL fallback, plus the
 * removal of the public download_file() entry point. Adapted only in the processor factory,
 * which seeds a real provider instance via {@see \core_ai\manager} instead of `new provider()`,
 * since {@see provider} extends `\core_ai\provider`, whose constructor requires a persisted
 * instance record (enabled flag, name and JSON config).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso;

use core_ai\aiactions\generate_image;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the image generation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\process_generate_image::class)]
final class process_generate_image_test extends \advanced_testcase {
    /** @var string Host pinned by the testable processor endpoint. */
    private const ENDPOINT_HOST = 'example.invalid';

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
     * The mock handler is returned so tests can assert how many queued responses were consumed.
     *
     * @param array $responses Queue of Response/exception objects for the MockHandler.
     * @return MockHandler
     */
    private function set_mock_http(array $responses): MockHandler {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));
        return $mock;
    }

    /**
     * Build an image generation action for the current user.
     *
     * @param string $prompt
     * @return generate_image
     */
    private function make_action(string $prompt = 'A friendly robot'): generate_image {
        global $USER;
        return new generate_image(
            \context_system::instance()->id,
            (int) $USER->id,
            $prompt,
            'hd',
            'square',
            1,
            'vivid'
        );
    }

    /**
     * Build a testable processor for a given prompt.
     *
     * The processor is an anonymous subclass returning a fixed endpoint, avoiding any network
     * at construction, and exposing the protected request builder for direct unit testing.
     *
     * @param string $prompt
     * @return process_generate_image
     */
    private function make_processor(string $prompt = 'A friendly robot'): process_generate_image {
        return new class ($this->provider, $this->make_action($prompt)) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/images/generations');
            }

            /**
             * Public wrapper over the protected request builder, for direct unit testing.
             *
             * @param string $userid
             * @return \Psr\Http\Message\RequestInterface
             */
            public function expose_create_request_object(string $userid): \Psr\Http\Message\RequestInterface {
                return $this->create_request_object($userid);
            }
        };
    }

    /**
     * Build a small valid PNG binary with GD.
     *
     * @return string
     */
    private function png_bytes(): string {
        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);
        return $png;
    }

    /**
     * Build the JSON API response wrapping one image entry.
     *
     * @param array $entry The data[0] entry (url or b64_json).
     * @return Response
     */
    private function api_response(array $entry): Response {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['data' => [$entry]]));
    }

    /**
     * Count the real files (not directory placeholders) stored in any user draft area.
     *
     * @return int
     */
    private function count_draft_files(): int {
        global $DB;
        return $DB->count_records_select('files', "component = 'user' AND filearea = 'draft' AND filename <> '.'");
    }

    /**
     * Assert that the image action failed with the invalid-image error and that nothing was stored.
     *
     * The rejection reason must reach the developer debugging channel only.
     *
     * @param \core_ai\aiactions\responses\response_base $response
     * @param int $draftfilesbefore
     */
    private function assert_rejected_without_storing($response, int $draftfilesbefore): void {
        $this->assertFalse($response->get_success());
        $this->assertSame(
            get_string('responseinvalidaimage', 'aiprovider_datacurso'),
            $response->get_errormessage()
        );
        $this->assertSame($draftfilesbefore, $this->count_draft_files());
        $this->assertNotEmpty($this->getDebuggingMessages(), 'The rejection reason must be logged via debugging()');
        $this->resetDebugging();
    }

    /**
     * An empty prompt is rejected locally, before any call to the AI service.
     *
     * MDL-UNIT-010: prompt validation on image generation.
     */
    public function test_empty_prompt_is_rejected_before_network(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Empty MockHandler: if the processor reached the network the handler would raise a
        // different error, so requiring the moodle_exception proves the request never left.
        $this->set_mock_http([]);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Empty prompt/');

        $this->make_processor('')->process();
    }

    /**
     * The URL fallback refuses plain http:// image locations without fetching them.
     *
     * SEC-002: https-only on the image URL fallback.
     */
    public function test_url_fallback_rejects_plain_http(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        // Only the API response is queued: a fetch attempt would exhaust the mock and error out.
        $mock = $this->set_mock_http([
            $this->api_response(['url' => 'http://' . self::ENDPOINT_HOST . '/images/out.png']),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
        $this->assertSame(0, $mock->count());
    }

    /**
     * The URL fallback refuses hosts other than the provider endpoint host.
     *
     * SEC-002: host pinning on the image URL fallback.
     */
    public function test_url_fallback_rejects_foreign_host(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $mock = $this->set_mock_http([
            $this->api_response(['url' => 'https://cdn.example.com/images/out.png']),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
        $this->assertSame(0, $mock->count());
    }

    /**
     * The URL fallback refuses paths whose extension is not an allowed raster image type.
     *
     * SEC-002: extension whitelist on the image URL fallback.
     */
    public function test_url_fallback_rejects_disallowed_extension(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $mock = $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.svg']),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
        $this->assertSame(0, $mock->count());
    }

    /**
     * The URL fallback never follows redirects, so the host pin cannot be bypassed via Location.
     *
     * SEC-002: redirects disabled on the image URL fallback.
     */
    public function test_url_fallback_does_not_follow_redirects(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $mock = $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(302, ['Location' => 'https://' . self::ENDPOINT_HOST . '/images/real.png']),
            new Response(200, ['Content-Type' => 'image/png'], $this->png_bytes()),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
        // The redirect target response must remain unconsumed.
        $this->assertSame(1, $mock->count());
    }

    /**
     * A Content-Length above the size cap is rejected before reading the body.
     *
     * SEC-002: size cap on the image URL fallback (declared length).
     */
    public function test_url_fallback_rejects_oversized_content_length(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(
                200,
                ['Content-Type' => 'image/png', 'Content-Length' => (string) (20 * 1024 * 1024)],
                $this->png_bytes()
            ),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
    }

    /**
     * A body larger than the size cap is rejected even when no Content-Length is declared.
     *
     * SEC-002: size cap on the image URL fallback (streamed length).
     */
    public function test_url_fallback_rejects_oversized_body_without_content_length(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $oversized = $this->png_bytes() . str_repeat('A', 10 * 1024 * 1024);
        $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(200, ['Content-Type' => 'image/png'], $oversized),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
    }

    /**
     * Response headers are not trusted: non-image bytes served as image/png are rejected.
     *
     * SEC-002: image sniffing on the image URL fallback.
     */
    public function test_url_fallback_rejects_non_image_bytes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(200, ['Content-Type' => 'image/png'], '<html>not an image</html>'),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
    }

    /**
     * A valid PNG served from the pinned host over https is stored with a safe generated name.
     *
     * SEC-002: happy path of the hardened image URL fallback.
     */
    public function test_url_fallback_saves_valid_png(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $imageurl = 'https://' . self::ENDPOINT_HOST . '/images/Out%20File.PNG';
        $this->set_mock_http([
            $this->api_response(['url' => $imageurl]),
            new Response(200, ['Content-Type' => 'application/octet-stream'], $this->png_bytes()),
        ]);

        $response = $this->make_processor()->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $data = $response->get_response_data();
        $this->assertInstanceOf(\stored_file::class, $data['draftfile']);
        $this->assertMatchesRegularExpression('/^datacurso_image_\d+\.png$/', $data['draftfile']->get_filename());
        $this->assertSame('image/png', $data['draftfile']->get_mimetype());
        $this->assertSame($imageurl, $data['sourceurl']);
        $this->assertSame($before + 1, $this->count_draft_files());
        // Watermarking is best-effort and may log when FreeType is missing.
        $this->resetDebugging();
    }

    /**
     * A valid inline base64 PNG is stored with the generated PNG name.
     *
     * SEC-002: the image gate keeps accepting genuine inline images.
     */
    public function test_b64_valid_png_is_saved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $this->set_mock_http([
            $this->api_response(['b64_json' => base64_encode($this->png_bytes())]),
        ]);

        $response = $this->make_processor()->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $data = $response->get_response_data();
        $this->assertInstanceOf(\stored_file::class, $data['draftfile']);
        $this->assertMatchesRegularExpression('/^datacurso_image_\d+\.png$/', $data['draftfile']->get_filename());
        $this->assertSame('image/png', $data['draftfile']->get_mimetype());
        $this->assertSame($before + 1, $this->count_draft_files());
        $this->resetDebugging();
    }

    /**
     * Inline base64 content that is not a supported image is rejected and nothing is stored.
     *
     * SEC-002: image gate on the inline base64 path.
     */
    public function test_b64_non_image_is_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $this->set_mock_http([
            $this->api_response(['b64_json' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>')]),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
    }

    /**
     * The old public download entry point is gone: fetching is an internal, validated step.
     *
     * SEC-002: no public raw download API on the processor.
     */
    public function test_download_file_is_not_part_of_public_api(): void {
        $this->assertFalse((new \ReflectionClass(process_generate_image::class))->hasMethod('download_file'));
    }

    /**
     * Building the request object must not log the request body (it contains the user prompt).
     *
     * SEC-003: no prompt leakage through debugging output.
     */
    public function test_create_request_object_does_not_log_prompt(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $request = $this->make_processor('Top secret prompt')->expose_create_request_object('1');

        $this->assertStringContainsString('Top secret prompt', (string) $request->getBody());
        $this->assertDebuggingNotCalled();
    }

    /**
     * The endpoint is resolved once per request, even when the image URL fallback pins the host.
     *
     * Every get_endpoint() call on the real processor performs a network round trip to the shop
     * (license check), so the request flow must cache the resolved URI.
     */
    public function test_endpoint_is_resolved_once_per_request(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(200, ['Content-Type' => 'image/png'], $this->png_bytes()),
        ]);

        $processor = new class ($this->provider, $this->make_action()) extends process_generate_image {
            /** @var int Number of times the endpoint was requested. */
            public static int $endpointcalls = 0;

            #[\Override]
            protected function get_endpoint(): UriInterface {
                self::$endpointcalls++;
                return new Uri('https://example.invalid/provider/images/generations');
            }
        };
        $processor::$endpointcalls = 0;

        $response = $processor->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $this->assertSame(1, $processor::$endpointcalls);
        // Watermarking is best-effort and may log when FreeType is missing.
        $this->resetDebugging();
    }

    /**
     * When the endpoint cannot be resolved for host pinning, the URL fallback rejects the image
     * gracefully instead of throwing after a successful AI response.
     */
    public function test_url_fallback_rejects_when_endpoint_cannot_be_resolved(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        // Empty MockHandler: any fetch attempt would error out with a different failure.
        $this->set_mock_http([]);

        $processor = new class ($this->provider, $this->make_action()) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                throw new \moodle_exception('licensekey_missing', 'aiprovider_datacurso');
            }

            /**
             * Public wrapper over the protected success handler, for direct unit testing.
             *
             * @param ResponseInterface $response
             * @return array
             */
            public function expose_handle_api_success(ResponseInterface $response): array {
                return $this->handle_api_success($response);
            }
        };

        $result = $processor->expose_handle_api_success(
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png'])
        );

        $this->assertFalse($result['success']);
        $this->assertSame(get_string('responseinvalidaimage', 'aiprovider_datacurso'), $result['errormessage']);
        $this->assertSame($before, $this->count_draft_files());
        $this->assertDebuggingCalledCount(1);
    }

    /**
     * An image whose header claims a huge pixel area is rejected before any decoding attempt.
     *
     * The IHDR width/height (big-endian at offsets 16 and 20) are patched to 30000x30000; PHP's
     * getimagesize() reads the header without CRC validation, so this mimics a decompression bomb.
     */
    public function test_oversized_pixel_dimensions_are_rejected(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $dimension = pack('N', 30000);
        $bomb = substr_replace($this->png_bytes(), $dimension . $dimension, 16, 8);

        $this->set_mock_http([
            $this->api_response(['b64_json' => base64_encode($bomb)]),
        ]);

        $response = $this->make_processor()->process();

        $this->assert_rejected_without_storing($response, $before);
    }

    /**
     * An explicit :443 port on the pinned https host is accepted (it is the default https port).
     */
    public function test_url_fallback_accepts_explicit_port_443_on_pinned_host(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $imageurl = 'https://' . self::ENDPOINT_HOST . ':443/a.png';
        $this->set_mock_http([
            $this->api_response(['url' => $imageurl]),
            new Response(200, ['Content-Type' => 'image/png'], $this->png_bytes()),
        ]);

        $response = $this->make_processor()->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $data = $response->get_response_data();
        $this->assertInstanceOf(\stored_file::class, $data['draftfile']);
        $this->assertSame($imageurl, $data['sourceurl']);
        $this->assertSame($before + 1, $this->count_draft_files());
        $this->resetDebugging();
    }

    /**
     * A streamed body of exactly MAX_IMAGE_BYTES (no Content-Length) is accepted: the cap is exclusive.
     *
     * The PNG is padded with trailing zero bytes; getimagesize() only inspects the header, so the
     * padded bytes still sniff as PNG.
     */
    public function test_url_fallback_accepts_body_exactly_at_cap(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $png = $this->png_bytes();
        $body = $png . str_repeat("\0", 10 * 1024 * 1024 - strlen($png));
        $this->assertSame(10 * 1024 * 1024, strlen($body));

        $this->set_mock_http([
            $this->api_response(['url' => 'https://' . self::ENDPOINT_HOST . '/images/out.png']),
            new Response(200, ['Content-Type' => 'image/png'], $body),
        ]);

        $response = $this->make_processor()->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $this->assertInstanceOf(\stored_file::class, $response->get_response_data()['draftfile']);
        $this->assertSame($before + 1, $this->count_draft_files());
        $this->resetDebugging();
    }

    /**
     * A WEBP image is stored with its own extension and MIME type; the best-effort watermark step
     * (which does not support WEBP) must not fail the request.
     */
    public function test_webp_payload_is_stored_without_watermark_failure(): void {
        if (!function_exists('imagewebp')) {
            $this->markTestSkipped('GD without WEBP support');
        }

        $this->resetAfterTest();
        $this->setAdminUser();
        $before = $this->count_draft_files();

        $image = imagecreatetruecolor(8, 8);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 200, 30));
        ob_start();
        imagewebp($image);
        $webp = ob_get_clean();
        imagedestroy($image);

        $this->set_mock_http([
            $this->api_response(['b64_json' => base64_encode($webp)]),
        ]);

        $response = $this->make_processor()->process();

        $this->assertTrue($response->get_success(), $response->get_errormessage() ?? '');
        $data = $response->get_response_data();
        $this->assertInstanceOf(\stored_file::class, $data['draftfile']);
        $this->assertMatchesRegularExpression('/^datacurso_image_\d+\.webp$/', $data['draftfile']->get_filename());
        $this->assertSame('image/webp', $data['draftfile']->get_mimetype());
        $this->assertSame($before + 1, $this->count_draft_files());
        $this->resetDebugging();
    }
}
