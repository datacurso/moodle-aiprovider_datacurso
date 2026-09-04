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

require_once($CFG->libdir . '/filelib.php');

/**
 * Class datacurso_api_base
 * Base class for interacting with Datacurso APIs.
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datacurso_api_base {
    /** @var string $baseurl The base URL for Datacurso API requests */
    protected $baseurl;

    /** @var string|null $licensekey */
    protected $licensekey;

    /** @var object|null $instanceprovider */
    protected $instanceprovider;

    /**
     * Constructor.
     *
     * @param string $baseurl
     * @param string|null $licensekey
     */
    public function __construct(string $baseurl, ?string $licensekey = null) {
        global $DB;

        $manager = new \core_ai\manager($DB);
        $instances = $manager->get_provider_instances();
        $licensekey = '';

        foreach ($instances as $instance) {
            if ($instance->get_name() === 'aiprovider_datacurso' && $instance->enabled === true) {
                $config = $instance->config;
                if (!empty($config['licensekey'])) {
                    $this->instanceprovider = $instance;
                    $licensekey = $config['licensekey'];
                    break;
                }
            }
        }
        if ($this->instanceprovider == null) {
            throw new \moodle_exception('instance_disabled', 'aiprovider_datacurso');
        }
        $this->baseurl = rtrim($baseurl, '/');
        $this->licensekey = $licensekey;
    }

    /**
     * Returns the base URL for Datacurso API requests.
     */
    public function get_base_url(): string {
        return $this->baseurl . '/';
    }

    /**
     * Returns a persistent site UUID used to identify this Moodle site across the AI services.
     *
     * Unlike md5($CFG->wwwroot), this UUID is stable even if the site URL changes, and is what the
     * services use as `site_id` to isolate per-user rate-limit counters between sites that share the
     * same license (Moodle user ids are only unique within a site).
     *
     * @return string
     */
    public static function get_site_uuid(): string {
        $siteuuid = get_config('aiprovider_datacurso', 'site_uuid');
        if (!empty($siteuuid)) {
            return (string) $siteuuid;
        }

        $siteuuid = \core\uuid::generate();
        set_config('site_uuid', $siteuuid, 'aiprovider_datacurso');
        return $siteuuid;
    }

    /**
     * Download a file from Datacurso API.
     *
     * @param string $endpoint The API endpoint for the file download.
     * @param string $filename The desired name for the downloaded file.
     * @param array $filerecord Additional file record information.
     */
    public function download_file($endpoint, $filename, $filerecord = []): ?\stored_file {
        global $USER;

        $baseurl = $this->get_base_url();
        $packageurl = $baseurl . ltrim($endpoint, '/');

        $userid = $USER->id;
        $draftid = file_get_unused_draft_itemid();

        $fs = get_file_storage();
        $context = \context_user::instance($userid);

        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        $fileinfo = array_merge($fileinfo, $filerecord);

        $options = [];
        $options['headers'] = [
            'License-Key: ' . $this->licensekey,
        ];

        return $fs->create_file_from_url($fileinfo, $packageurl, $options, true);
    }

    /**
     * Generic handler for HTTP calls to Datacurso API.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The API path/endpoint.
     * @param array $payload The request body or GET parameters.
     * @param array $headers Additional HTTP headers.
     * @return array|null The decoded JSON response array, or null on failure.
     */
    protected function send_request(string $method, string $path, $payload = [], array $headers = []): ?array {
        global $USER, $CFG;

        if (empty($this->licensekey)) {
            debugging('Cannot make this request: invalid license key', DEBUG_DEVELOPER);
            throw new \moodle_exception('invalidlicensekey', 'aiprovider_datacurso');
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Resolve the configured service for this path; null when the path is not mapped.
        $serviceid = \aiprovider_datacurso\local\ratelimiter::resolve_service_for_path($path);

        $curl = new \curl();
        $baseheaders = [
            'License-Key: ' . $this->licensekey,
        ];

        // Forward the configured per-service rate limit to the Python service, which enforces it
        // centrally against the user's accumulated credit consumption within the window. The
        // resolved sub-action determines the look-ahead credit estimate (X-RateLimit-MaxPerAction).
        $actionkey = \aiprovider_datacurso\local\ratelimiter::resolve_action_key(
            $serviceid,
            $path,
            is_array($payload) ? $payload : []
        );
        $ratelimiter = new \aiprovider_datacurso\local\ratelimiter($this->instanceprovider);
        $baseheaders = array_merge($baseheaders, $ratelimiter->get_rate_limit_headers($serviceid, $actionkey));

        $headers = array_merge($baseheaders, $headers);

        $url = $this->baseurl . $path;

        $defaultpayload = [
            'site_id' => self::get_site_uuid(),
            'userid' => $payload['userid'] ?? $USER->id,
            'timezone' => \core_date::get_user_timezone(),
            'lang' => $payload['lang'] ?? current_language(),
            'site_url' => $CFG->wwwroot,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                // Encode here (not in execute_request()) so the raw multibyte text (accented
                // characters, etc.) is preserved: JSON_UNESCAPED_UNICODE must be set on the
                // encode call that actually produces the wire body.
                $payload = json_encode(array_merge($payload, $defaultpayload), JSON_UNESCAPED_UNICODE);
                break;

            case 'PUT':
            case 'UPLOAD':
                $payload = array_merge($payload, $defaultpayload);
                break;

            default:
                break;
        }

        $response = $this->execute_request($curl, $method, $url, $payload, $headers);

        if (!$response) {
            debugging('Empty response from Datacurso API', DEBUG_DEVELOPER);
            throw new \moodle_exception('emptyresponse', 'aiprovider_datacurso');
        }

        if ($curl->error) {
            debugging('cURL error (' . $curl->error . ')', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'aiprovider_datacurso', '', $curl->error);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        // Handle API 403 errors.
        if ($httpcode == 403) {
            $decodedresponse = json_decode($response, true);

            $detail = $decodedresponse['detail'] ?? '';
            if ($detail === 'rate_limit_exceeded') {
                $resetat = (int)($decodedresponse['reset_at'] ?? 0);
                $retryat = $resetat > 0
                    ? userdate($resetat, get_string('strftimedatetime', 'langconfig'))
                    : '';
                debugging('Rate limit exceeded for this service', DEBUG_DEVELOPER);
                throw new \moodle_exception('error_ratelimit_exceeded', 'aiprovider_datacurso', '', $retryat);
            }
            if ($detail === 'tokens_not_sufficient') {
                debugging('Not enough tokens to make this request', DEBUG_DEVELOPER);
                throw new \moodle_exception('notenoughtokens', 'aiprovider_datacurso');
            }
            if ($detail === 'license_not_allowed') {
                debugging('License not allowed to make this request', DEBUG_DEVELOPER);
                throw new \moodle_exception('license_not_allowed', 'aiprovider_datacurso');
            }

            debugging('Unknown error from Datacurso API', DEBUG_DEVELOPER);
            throw new \moodle_exception('forbidden', 'aiprovider_datacurso');
        }

        if ($httpcode >= 400) {
            debugging("HTTP error {$httpcode} from Datacurso API: {$response}", DEBUG_DEVELOPER);
            debugging('PAYLOAD: ' . json_encode($payload), DEBUG_DEVELOPER);
            throw new \moodle_exception('httperror', 'aiprovider_datacurso', '', $httpcode);
        }

        $decodedresponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('JSON decode error: ' . json_last_error_msg() . '. Response: ' . $response, DEBUG_DEVELOPER);
            throw new \moodle_exception('jsondecodeerror', 'aiprovider_datacurso', '', json_last_error_msg());
        }

        return $decodedresponse;
    }

    /**
     * Execute the already-built request against the API and return the raw response.
     *
     * Isolated from send_request() so tests can override just the cURL boundary (see
     * tests/fixtures/test_header_client.php) while the rest of send_request() -- including
     * rate-limit header construction -- still executes for real.
     *
     * @param \curl $curl cURL client to execute the request with.
     * @param string $method The HTTP method (GET, POST, PUT, DELETE, UPLOAD).
     * @param string $url Full request URL.
     * @param mixed $payload Request body or GET parameters, already merged with defaults.
     * @param array $headers Request headers, including the rate limit headers.
     * @return string|null Raw response body, or null on failure.
     */
    protected function execute_request(\curl $curl, string $method, string $url, $payload, array $headers): ?string {
        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, $payload, $options);
                break;

            case 'POST':
                // The payload is already an encoded JSON string here (see send_request()).
                $response = $curl->post($url, $payload, $options);
                break;

            case 'PUT':
                $response = $curl->put($url, $payload, $options);
                break;

            case 'DELETE':
                $response = $curl->delete($url, $payload, $options);
                break;

            case 'UPLOAD':
                $response = $curl->post($url, $payload, $options);
                break;

            default:
                throw new \coding_exception('Invalid HTTP method: ' . $method);
        }

        return $response;
    }

    /**
     * Standard JSON API call.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The API path/endpoint.
     * @param array $body The request body or GET parameters.
     * @return array|null The decoded JSON response array, or null on failure.
     */
    public function request(string $method, string $path, array $body = []): ?array {
        $headers = ['Content-Type: application/json'];
        return $this->send_request($method, $path, $body, $headers);
    }

    /**
     * Upload a file using multipart/form-data.
     *
     * Takes a stored_file because that is what callers hold: Moodle keeps files in
     * the file storage API, not on disk. The temporary copy needed by cURL is made
     * and removed here so no caller has to manage it.
     *
     * @param string $path Relative endpoint. Example: '/upload-file'.
     * @param \stored_file $file File to upload, from the Moodle file storage API.
     * @param array $extraparams Extra POST parameters to include in the upload request.
     * @return array|null Decoded response from the API.
     * @throws \coding_exception If the temporary copy cannot be created.
     * @throws \Exception If the request fails.
     */
    public function upload_file(
        string $path,
        \stored_file $file,
        array $extraparams = []
    ): ?array {
        // Create a temporary copy of the file content on disk.
        $filepath = $file->copy_content_to_temp();
        $filename = $file->get_filename();
        $mimetype = $file->get_mimetype();

        if (!$filepath || !file_exists($filepath)) {
            throw new \coding_exception('Temporary file could not be created for upload.');
        }

        try {
            $postdata = array_merge($extraparams, [
                'file' => new \CURLFile($filepath, $mimetype, $filename),
            ]);

            return $this->send_request('UPLOAD', $path, $postdata);
        } finally {
            // Always clean up the temporary file, including when the request throws.
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
    }

    /**
     * Check if the license is for European Union.
     *
     * @return bool
     */
    public function is_for_ue(): bool {
        $datacursoapi = new datacurso_api();
        $response = $datacursoapi->get('tokens/saldo');
        return $response['is_for_eu'] == true;
    }
}
