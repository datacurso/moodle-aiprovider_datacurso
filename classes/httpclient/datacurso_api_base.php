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

use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\ratelimiter;
use aiprovider_datacurso\local\request_audit;
use aiprovider_datacurso\local\transport_policy;

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
    /**
     * Path prefixes on which each client sends the user's timezone; a client not listed sends it on every path.
     *
     * The AI services read the timezone only on their CV and job matching routes, so their other
     * requests leave it out. The course service uses it on every route.
     */
    private const TIMEZONE_PATH_PREFIXES = [
        request_audit::CLIENT_AI_SERVICES => ['/cv/', '/jobmatch/'],
    ];

    /** @var string $baseurl The base URL for Datacurso API requests */
    protected $baseurl;

    /** @var string|null $licensekey */
    protected $licensekey;

    /** @var object|null $instanceprovider */
    protected $instanceprovider;

    /** @var plugin_identity Says which plugin makes the requests, and its release. */
    protected $pluginidentity;

    /** @var \context|null Context the requests are logged in; the system context when null. */
    protected $auditcontext = null;

    /** @var int|null User the requests are made for, when the consumer plugin names one. */
    protected $auditrelateduserid = null;

    /**
     * Constructor.
     *
     * @param string $baseurl
     * @param string|null $licensekey
     * @param string|null $callercomponent Frankenstyle name of the plugin that makes the requests, for example
     *        "local_coursegen". Left null, no X-Plugin-* header is sent (see plugin_identity).
     */
    public function __construct(string $baseurl, ?string $licensekey = null, ?string $callercomponent = null) {
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
        $this->pluginidentity = new plugin_identity($callercomponent);
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
     * Build the headers that say which plugin makes the request and its release.
     *
     * @return string[] Header lines; empty when the client was not given a plugin.
     */
    public function get_plugin_headers(): array {
        return $this->pluginidentity->get_headers();
    }

    /**
     * Set the context, and optionally the user, the requests of this client are logged with.
     *
     * Every request is recorded with the event external_request_completed, in the system context
     * unless the plugin making it tells the client where the request comes from. The user id sent
     * in the payload is never read for the log: only the user named here is recorded as related user.
     *
     * @param \context $context Context the requests are made in, for example a course.
     * @param int|null $relateduserid Moodle id of the user the requests are made for, when not the current user.
     * @return static
     */
    public function set_audit_context(\context $context, ?int $relateduserid = null): static {
        $this->auditcontext = $context;
        $this->auditrelateduserid = $relateduserid;
        return $this;
    }

    /**
     * Name of this client in the audit log, which also decides the fields it sends.
     *
     * @return string One of the request_audit::CLIENT_* constants.
     */
    protected function get_client_name(): string {
        if ($this instanceof ai_services_api) {
            return request_audit::CLIENT_AI_SERVICES;
        }
        if ($this instanceof ai_course_api) {
            return request_audit::CLIENT_AI_COURSE;
        }
        return request_audit::CLIENT_OTHER;
    }

    /**
     * Describe a request for the audit log, before it is sent.
     *
     * @param string $method HTTP method, or UPLOAD.
     * @param string $path Endpoint path.
     * @return request_audit
     */
    private function create_request_audit(string $method, string $path): request_audit {
        $service = ratelimiter::resolve_service_for_path($path) ?? $this->pluginidentity->get_component() ?? 'unknown';

        return new request_audit(
            $this->get_client_name(),
            $service,
            strtoupper($method),
            $path,
            $this->auditcontext,
            $this->auditrelateduserid
        );
    }

    /**
     * Whether a request of this client to the given path carries the user's timezone.
     *
     * @param string $path Endpoint path, starting with "/".
     * @return bool
     */
    private function sends_timezone(string $path): bool {
        $prefixes = self::TIMEZONE_PATH_PREFIXES[$this->get_client_name()] ?? null;
        if ($prefixes === null) {
            return true;
        }
        foreach ($prefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refuse a request that must not leave the site: no license key or no HTTPS.
     *
     * @param string $url Absolute URL of the request.
     * @throws \moodle_exception invalidlicensekey or insecuretransport.
     */
    private function require_sendable(string $url): void {
        if (empty($this->licensekey)) {
            debugging('Cannot make this request: invalid license key', DEBUG_DEVELOPER);
            throw new \moodle_exception('invalidlicensekey', 'aiprovider_datacurso');
        }
        transport_policy::require_secure_url($url);
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

        $audit = $this->create_request_audit('GET', '/' . ltrim($endpoint, '/'));
        try {
            $this->require_sendable($packageurl);
        } catch (\moodle_exception $e) {
            $audit->refused($e);
            throw $e;
        }

        $userid = $USER->id;
        $draftid = file_get_unused_draft_itemid();

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
        $licenseheaders = ['License-Key: ' . $this->licensekey];
        $pluginheaders = $this->get_plugin_headers();
        $options['headers'] = array_merge($licenseheaders, $pluginheaders);

        try {
            $file = $this->fetch_file_from_url($fileinfo, $packageurl, $options);
        } catch (\Throwable $e) {
            $audit->failed($e);
            throw $e;
        }
        // The file is only stored when the service answered 200.
        $audit->succeeded(200);
        return $file;
    }

    /**
     * Fetch a file from a URL into the file storage.
     *
     * The file is downloaded with the same transport rules as the other requests (see
     * transport_policy): a redirect answer is not followed, so the license key never reaches
     * another host, and only an answer 200 is stored.
     *
     * @param array $fileinfo File record of the file to create.
     * @param string $url URL to fetch the file from.
     * @param array $options Options of the request, such as its headers.
     * @return \stored_file|null
     * @throws \file_exception When the file cannot be fetched or stored.
     */
    protected function fetch_file_from_url(array $fileinfo, string $url, array $options): ?\stored_file {
        $tempfile = make_request_directory() . DIRECTORY_SEPARATOR . 'download';
        $curl = $this->create_curl();
        $curloptions = transport_policy::curl_options(transport_policy::DOWNLOAD_TIMEOUT);
        $curloptions['CURLOPT_HTTPHEADER'] = $options['headers'] ?? [];
        $curloptions['filepath'] = $tempfile;

        $result = $curl->download_one($url, null, $curloptions);
        $httpstatus = (int)($curl->get_info()['http_code'] ?? 0);
        if ($result !== true || $curl->error || $httpstatus !== 200 || !file_exists($tempfile)) {
            debugging('Datacurso download failed: HTTP ' . $httpstatus . ', cURL errno ' . (int)$curl->errno, DEBUG_DEVELOPER);
            throw new \file_exception('storedfileproblem', 'Cannot fetch file from URL');
        }

        return get_file_storage()->create_file_from_pathname($fileinfo, $tempfile);
    }

    /**
     * Create the cURL wrapper used for the requests.
     *
     * Seam for tests, so the transport can be replaced without touching the network.
     *
     * @return \curl
     */
    protected function create_curl(): \curl {
        return new \curl();
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
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Every request is logged once with its outcome; refused means nothing left the site.
        $audit = $this->create_request_audit($method, $path);
        try {
            $this->require_sendable($this->baseurl . $path);
        } catch (\moodle_exception $e) {
            $audit->refused($e);
            throw $e;
        }

        $httpstatus = 0;
        try {
            $decodedresponse = $this->perform_request($method, $path, $payload, $headers, $httpstatus);
        } catch (\Throwable $e) {
            $audit->failed($e, $httpstatus);
            throw $e;
        }
        $audit->succeeded($httpstatus);

        return $decodedresponse;
    }

    /**
     * Send a request that passed the local checks and decode its answer.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, UPLOAD).
     * @param string $path Relative endpoint, starting with "/".
     * @param mixed $payload Data for request (array, string, or multipart).
     * @param array $headers Extra headers if needed.
     * @param int $httpstatus Set to the HTTP status of the answer, 0 while there is none.
     * @return array|null
     * @throws \Exception
     */
    private function perform_request(string $method, string $path, $payload, array $headers, int &$httpstatus): ?array {
        global $USER, $CFG;

        // Resolve the configured service for this path; null when the path is not mapped.
        $serviceid = ratelimiter::resolve_service_for_path($path);

        $curl = $this->create_curl();
        $licenseheaders = ['License-Key: ' . $this->licensekey];
        $pluginheaders = $this->get_plugin_headers();
        $baseheaders = array_merge($licenseheaders, $pluginheaders);

        // Forward the configured per-service rate limit to the Python service, which enforces it
        // centrally against the user's accumulated credit consumption within the window. The
        // resolved sub-action determines the look-ahead credit estimate (X-RateLimit-MaxPerAction).
        $actionkey = ratelimiter::resolve_action_key(
            $serviceid,
            $path,
            is_array($payload) ? $payload : []
        );
        $ratelimiter = new ratelimiter($this->instanceprovider);
        $baseheaders = array_merge($baseheaders, $ratelimiter->get_rate_limit_headers($serviceid, $actionkey));

        $headers = array_merge($baseheaders, $headers);

        $url = $this->baseurl . $path;

        $defaultpayload = [
            'site_id' => self::get_site_uuid(),
            'userid' => $payload['userid'] ?? $USER->id,
            'lang' => $payload['lang'] ?? current_language(),
            'site_url' => $CFG->wwwroot,
        ];
        // The timezone only goes to the routes that use it.
        if ($this->sends_timezone($path)) {
            $defaultpayload['timezone'] = \core_date::get_user_timezone();
        }

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
        $httpstatus = (int)($curl->get_info()['http_code'] ?? 0);

        if (transport_policy::is_redirect($httpstatus)) {
            // A redirect is never followed: it would hand the license key and the payload to another host.
            debugging("Redirect answer {$httpstatus} from Datacurso API was not followed", DEBUG_DEVELOPER);
            throw new \moodle_exception('httperror', 'aiprovider_datacurso', '', $httpstatus);
        }

        if (!$response) {
            debugging('Empty response from Datacurso API', DEBUG_DEVELOPER);
            throw new \moodle_exception('emptyresponse', 'aiprovider_datacurso');
        }

        if ($curl->error) {
            // The raw libcurl text may contain hostnames: keep it for developers only.
            debugging('cURL error errno ' . $curl->errno . ' (' . $curl->error . ')', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'aiprovider_datacurso', '', (int)$curl->errno);
        }

        $httpcode = $httpstatus;

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

        if ($httpcode == 426) {
            $upgrade = new upgrade_required_response($response);
            throw $upgrade->to_exception();
        }

        if ($httpcode >= 400) {
            // Never log the body: it may contain upstream internals. Log its size instead.
            debugging("HTTP error {$httpcode} from Datacurso API (" . strlen($response) . ' bytes)', DEBUG_DEVELOPER);
            throw new \moodle_exception('httperror', 'aiprovider_datacurso', '', $httpcode);
        }

        $decodedresponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('JSON decode error: ' . json_last_error_msg() . ' (' . strlen($response) . ' bytes)', DEBUG_DEVELOPER);
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
        // Verified HTTPS, no redirect (it would hand the license key to another host) and timeouts.
        $options = transport_policy::curl_options(transport_policy::AI_SERVICES_TIMEOUT) + [
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
     * The answer is resolved once and kept, because it is a property of the licence and not of
     * the request. See license_region for what that means when the shop cannot be reached.
     *
     * @return bool
     * @throws \moodle_exception When the region is not known yet and the shop cannot be asked.
     */
    public function is_for_ue(): bool {
        return license_region::is_european();
    }
}
