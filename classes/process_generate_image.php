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

namespace aiprovider_datacurso;

use core\http_client;
use core_ai\ai_image;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Processor for generating images via Datacurso AI provider.
 *
 * @package    aiprovider_datacurso
 * @copyright  Developer <developer@datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_generate_image extends abstract_processor {
    /** @var int Maximum accepted size (bytes) for a generated image, on both the URL and base64 paths. */
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** @var int Maximum accepted pixel area (25 MP; generated images are at most 1792x1024). */
    private const MAX_IMAGE_PIXELS = 25000000;

    /** @var int Chunk size (bytes) used while streaming a remote image. */
    private const READ_CHUNK_BYTES = 64 * 1024;

    /** @var int Timeout (seconds) for fetching a remote image. */
    private const FETCH_TIMEOUT = 30;

    /** @var string[] Path extensions accepted on the image URL fallback. */
    private const ALLOWED_URL_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    /** @var array<int, string> Image types accepted by the binary gate, mapped to the stored extension. */
    private const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_WEBP => 'webp',
    ];

    /** @var int Number of images to generate. */
    private int $numberimages = 1;

    /**
     * Returns the endpoint URI for this processor.
     *
     * @return UriInterface
     */
    #[\Override]
    protected function get_endpoint(): UriInterface {
        // Use the provider's configured base URL (docker service in local, prod URL in production).
        $baseurl = rtrim((new \aiprovider_datacurso\httpclient\ai_services_api())->get_base_url(), '/');
        return new Uri($baseurl . '/provider/images/generations');
    }

    /**
     * Build the request body for the API call.
     *
     * @param string $userid User ID for the request
     * @return array Request body parameters
     * @throws \moodle_exception If prompt is empty
     */
    #[\Override]
    protected function build_request_body(string $userid): array {
        global $USER;

        $finaluserid = $userid ?: $USER->id;
        $prompt = $this->action->get_configuration('prompttext');

        // Validate that prompt is not empty.
        if (empty($prompt)) {
            throw new \moodle_exception(
                'emptyprompt',
                'aiprovider_datacurso',
                '',
                null,
                'Image generation requires a prompt text'
            );
        }

        $aspectratio = $this->action->get_configuration('aspectratio') ?? 'square';
        $size = $this->calculate_size($aspectratio);

        return [
            'prompt' => $prompt,
            'n' => $this->numberimages,
            'size' => $size,
            'userid' => (string)$finaluserid,
        ];
    }

    /**
     * Convert aspect ratio selector to the Datacurso image size expected by the API.
     *
     * @param string $ratio Aspect ratio configuration value
     * @return string Datacurso size token (e.g. 1024x1024)
     */
    private function calculate_size(string $ratio): string {
        return match ($ratio) {
            'square' => '1024x1024',
            'landscape' => '1792x1024',
            'portrait' => '1024x1792',
            default => '1024x1024',
        };
    }

    /**
     * Create the HTTP request object ready to send.
     *
     * @param string $userid User ID for the request
     * @return RequestInterface PSR-7 request object
     */
    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        $body = json_encode($this->build_request_body($userid));

        $licensekey = $this->provider->config['licensekey'] ?? null;

        return new Request(
            'POST',
            $this->resolve_endpoint(),
            [
                'Content-Type' => 'application/json',
                'License-Key' => $licensekey,
                'User-Agent' => 'moodle-aiprovider-datacurso',
            ],
            $body
        );
    }

    /**
     * Handle successful API response.
     *
     * @param ResponseInterface $response PSR-7 response object
     * @return array Response data with success status and file information
     */
    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        $body = json_decode($response->getBody()->getContents());

        // Validate response structure.
        if (empty($body) || empty($body->data[0])) {
            debugging('Invalid API response: missing data array', DEBUG_DEVELOPER);
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('responseinvalidaimage', 'aiprovider_datacurso'),
            ];
        }

        $userid = (int)($this->action->get_configuration('userid') ?? 0);
        $b64 = $body->data[0]->b64_json ?? null;
        $imageurl = $body->data[0]->url ?? null;

        // Preferred path: the AI service returns the image inline as base64 (Gemini).
        if (!empty($b64)) {
            $binary = base64_decode($b64, true);
            if ($binary === false || $binary === '') {
                debugging('Invalid API response: could not decode base64 image', DEBUG_DEVELOPER);
                return [
                    'success' => false,
                    'errorcode' => 400,
                    'errormessage' => get_string('responseinvalidaimage', 'aiprovider_datacurso'),
                ];
            }
            $file = $this->save_to_draft_area($userid, $binary);
            $sourceurl = '';
        } else if (!empty($imageurl)) {
            // Fallback: a hosted URL was provided (e.g. OpenAI-style responses). The URL is only
            // fetched when it is https, on the provider endpoint host and with an image extension;
            // the fetched bytes are then validated exactly like the inline base64 path.
            $binary = $this->fetch_remote_image((string)$imageurl);
            $file = $binary !== null ? $this->save_to_draft_area($userid, $binary) : null;
            $sourceurl = $imageurl;
        } else {
            debugging('Invalid API response: no valid image format provided', DEBUG_DEVELOPER);
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('responseinvalidaimage', 'aiprovider_datacurso'),
            ];
        }

        // Every rejection (transport, size, host or image gate) is reported as an invalid image.
        if (!$file instanceof \stored_file) {
            return [
                'success' => false,
                'errorcode' => 400,
                'errormessage' => get_string('responseinvalidaimage', 'aiprovider_datacurso'),
            ];
        }

        return [
            'success' => true,
            'errorcode' => 200,
            'sourceurl' => $sourceurl,
            'revisedprompt' => $body->data[0]->revised_prompt ?? '',
            'draftfile' => $file,
        ];
    }

    /**
     * Fetch a generated image from the AI service host, enforcing https, host pinning, an
     * extension whitelist, no redirects and a hard size cap.
     *
     * Core's http_client already applies the site-wide cURL security helper (blocked hosts/ports).
     *
     * @param string $imageurl The image URL returned by the AI service.
     * @return string|null The raw image bytes, or null when the URL or the response is rejected.
     */
    private function fetch_remote_image(string $imageurl): ?string {
        $parts = parse_url($imageurl);
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https') {
            debugging('Rejected image URL: only https is allowed', DEBUG_DEVELOPER);
            return null;
        }

        // Resolving the endpoint may hit the network (license check); a failure here must degrade
        // to an invalid-image rejection, never to an exception after a successful AI response.
        try {
            $expectedhost = strtolower($this->resolve_endpoint()->getHost());
        } catch (\Throwable $e) {
            debugging('Image URL rejected: provider endpoint unavailable (' . get_class($e) . ')', DEBUG_DEVELOPER);
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? null;
        if ($host === '' || $host !== $expectedhost || ($port !== null && (int)$port !== 443)) {
            debugging('Rejected image URL: host does not match the AI service endpoint', DEBUG_DEVELOPER);
            return null;
        }

        $extension = strtolower(pathinfo(rawurldecode($parts['path'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_URL_EXTENSIONS, true)) {
            debugging('Rejected image URL: extension not allowed', DEBUG_DEVELOPER);
            return null;
        }

        try {
            $response = \core\di::get(http_client::class)->get($imageurl, [
                RequestOptions::ALLOW_REDIRECTS => false,
                RequestOptions::STREAM => true,
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::TIMEOUT => self::FETCH_TIMEOUT,
            ]);
        } catch (TransferException $e) {
            debugging('Image fetch failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        if ((int)$response->getStatusCode() !== 200) {
            debugging('Image fetch rejected: HTTP ' . $response->getStatusCode(), DEBUG_DEVELOPER);
            return null;
        }

        $contentlength = $response->getHeaderLine('Content-Length');
        if ($contentlength !== '' && (int)$contentlength > self::MAX_IMAGE_BYTES) {
            debugging('Image fetch rejected: declared Content-Length exceeds the size cap', DEBUG_DEVELOPER);
            return null;
        }

        // Read in chunks so a missing or misleading Content-Length cannot bypass the cap.
        $stream = $response->getBody();
        $binary = '';
        while (!$stream->eof()) {
            $binary .= $stream->read(self::READ_CHUNK_BYTES);
            if (strlen($binary) > self::MAX_IMAGE_BYTES) {
                $stream->close();
                debugging('Image fetch rejected: body exceeds the size cap', DEBUG_DEVELOPER);
                return null;
            }
        }
        $stream->close();

        return $binary;
    }

    /**
     * Validate that a binary string is a supported raster image and return its extension.
     *
     * Headers, URL extensions and the AI service claims are not trusted: the bytes are sniffed
     * with getimagesize(). Only PNG, JPEG and WEBP are accepted, up to MAX_IMAGE_PIXELS.
     *
     * @param string $binary Raw image bytes.
     * @return string|null File extension for the detected type, or null if not a supported image.
     */
    private function validate_image_binary(string $binary): ?string {
        if ($binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $tempfile = make_request_directory() . DIRECTORY_SEPARATOR . 'datacurso_image_check';
        file_put_contents($tempfile, $binary);
        $info = @getimagesize($tempfile);
        @unlink($tempfile);

        if ($info === false) {
            return null;
        }

        // Cap the declared pixel area so a tiny file cannot expand into a huge bitmap on decode.
        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width <= 0 || $height <= 0 || $width * $height > self::MAX_IMAGE_PIXELS) {
            debugging("Rejected generated image: dimensions {$width}x{$height} exceed the pixel cap", DEBUG_DEVELOPER);
            return null;
        }

        return self::ALLOWED_IMAGE_TYPES[$info[2]] ?? null;
    }

    /**
     * Validate the image bytes and store them into the user's draft file area.
     *
     * @param int $userid User ID that will own the draft file
     * @param string $imagebinary Raw image binary string
     * @return \stored_file|null Draft file reference, or null if the bytes are not a supported image
     * @throws \file_exception If file creation fails
     */
    private function save_to_draft_area(int $userid, string $imagebinary): ?\stored_file {
        global $CFG;

        require_once("{$CFG->libdir}/filelib.php");

        $extension = $this->validate_image_binary($imagebinary);
        if ($extension === null) {
            debugging('Rejected generated image: not a supported PNG/JPEG/WEBP image', DEBUG_DEVELOPER);
            return null;
        }

        $filename = 'datacurso_image_' . time() . '.' . $extension;
        $tempdst = make_request_directory() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempdst, $imagebinary);

        // Add watermark before saving to draft area. This is best-effort: it relies on GD compiled
        // with FreeType (imagettfbbox/imagettftext) and ai_image does not support every type (WEBP).
        // Where that is unavailable the watermark step throws; in that case we log and keep the
        // un-watermarked (already validated) image rather than failing the action.
        try {
            $image = new ai_image($tempdst);
            $image->add_watermark()->save();
        } catch (\Throwable $e) {
            debugging('Skipping image watermark (add_watermark failed): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $fileinfo = (object)[
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => file_get_unused_draft_itemid(),
            'filepath' => '/',
            'filename' => $filename,
        ];

        $fs = get_file_storage();
        return $fs->create_file_from_pathname($fileinfo, $tempdst);
    }

    /**
     * Query the AI API and validate response.
     *
     * @return array Response data with success status and file information
     */
    #[\Override]
    protected function query_ai_api(): array {
        $response = parent::query_ai_api();

        // Moodle expects draftfile to be defined for image generation.
        if (!empty($response['success']) && !empty($response['draftfile'])) {
            return $response;
        }

        // If not successful, return error process.
        return $response;
    }
}
