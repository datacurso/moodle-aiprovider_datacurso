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
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datacurso_api_base {
    /** Services that depend on the local Moodle webservice. */
    private const SERVICES_REQUIRING_WEBSERVICE = [
        'local_assign_ai',
        'local_forum_ai',
        'local_dttutor',
    ];

    /** @var string $baseurl The base URL for Datacurso API requests */
    protected $baseurl;

    /** @var string|null $licensekey The license key obtained from Datacurso SHOP */
    protected $licensekey;

    /**
     * Constructor.
     *
     * @param string $baseurl The base URL for Datacurso API requests.
     * @param string|null $licensekey The license key obtained from Datacurso SHOP.
     */
    public function __construct(string $baseurl, ?string $licensekey = null) {
        $this->baseurl = $baseurl;
        $this->licensekey = $licensekey ?? get_config('aiprovider_datacurso', 'licensekey');
    }

    /**
     * Returns the base URL for Datacurso API requests.
     * The URL is trimmed to remove any trailing slashes and a trailing slash is added.
     *
     * @return string The base URL for Datacurso API requests.
     */
    public function get_base_url(): string {
        return rtrim($this->baseurl, '/') . '/';
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
     * @param string $endpoint Relative endpoint (starting with "/").
     * @param string $filename The name of the file to download.
     * @param array $filerecord File record options as accepted by create_file_from_url(); defaults to storing in draft user area.
     * @return \stored_file|null The downloaded file.
     * @throws \Exception If the file cannot be created.
     */
    public function download_file($endpoint, $filename, $filerecord = []): ?\stored_file {
        global $USER;

        $baseurl = $this->get_base_url();
        $packageurl = $baseurl . ltrim($endpoint, '/');

        $userid = $USER->id;
        $draftid = file_get_unused_draft_itemid();

        // Store SCORM package in moodledata draft area directly from URL.
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

        $file = $fs->create_file_from_url($fileinfo, $packageurl, $options, true);
        return $file;
    }

    /**
     * Generic handler for HTTP calls to Datacurso API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, UPLOAD).
     * @param string $path   Relative endpoint (starting with "/").
     * @param mixed  $payload Data for request (array, string, or multipart).
     * @param array  $headers Extra headers if needed.
     * @return array|null
     * @throws \Exception
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
        $this->enforce_webservice_requirements($serviceid);

        $curl = new \curl();
        $baseheaders = [
            'License-Key: ' . $this->licensekey,
        ];

        // Forward the configured per-service rate limit to the Python service, which enforces it
        // centrally against the user's accumulated credit consumption within the window.
        $ratelimiter = new \aiprovider_datacurso\local\ratelimiter();
        $baseheaders = array_merge($baseheaders, $ratelimiter->get_rate_limit_headers($serviceid));

        $headers = array_merge($baseheaders, $headers);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        $url = $this->baseurl . $path;
        $response = null;

        $defaultpayload = [
            'site_id' => self::get_site_uuid(),
            'userid' => $payload['userid'] ?? $USER->id,
            'timezone' => \core_date::get_user_timezone(),
            'lang' => $payload['lang'] ?? current_language(),
        ];
        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, $payload, $options);
                break;
            case 'POST':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->post($url, json_encode($payload, JSON_UNESCAPED_UNICODE), $options);
                break;
            case 'PUT':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->put($url, $payload, $options);
                break;
            case 'DELETE':
                $response = $curl->delete($url, $payload, $options);
                break;
            case 'UPLOAD':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->post($url, $payload, $options);
                break;
            default:
                throw new \coding_exception('Invalid HTTP method: ' . $method);
        }

        if (!$response) {
            debugging('Empty response from Datacurso API', DEBUG_DEVELOPER);
            throw new \moodle_exception('emptyresponse', 'aiprovider_datacurso');
        }

        if ($curl->error) {
            debugging('cURL error (' . $curl->error . ')', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'aiprovider_datacurso', '', $curl->error);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;
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
     * Standard JSON API call.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, UPLOAD).
     * @param string $path   Relative endpoint. Example: '/create-course'.
     * @param array $body Data for request.
     * @return array|null
     * @throws \Exception
     */
    public function request(string $method, string $path, array $body = []): ?array {
        $headers = ['Content-Type: application/json'];
        return $this->send_request($method, $path, $body, $headers);
    }

    /**
     * Upload a file using multipart/form-data.
     *
     * @param string $path Relative endpoint. Example: '/upload-file'.
     * @param string $filepath Absolute path to the file to upload.
     * @param string|null $mimetype MIME type of the file (falls back to PHP detection when null).
     * @param string|null $filename Name to use for the uploaded file (defaults to basename of $filepath).
     * @param array $extraparams Extra POST parameters to include in the upload request.
     * @return array|null Decoded response from the API.
     * @throws \Exception If the local file does not exist or the request fails.
     */
    public function upload_file(
        string $path,
        string $filepath,
        ?string $mimetype = null,
        ?string $filename = null,
        array $extraparams = []
    ): ?array {
        if (!file_exists($filepath)) {
            $filename = basename($filepath);
            throw new \coding_exception("File not found: {$filename}");
        }

        $postdata = array_merge($extraparams, [
            'file' => new \CURLFile($filepath, $mimetype, $filename),
        ]);

        return $this->send_request('UPLOAD', $path, $postdata);
    }

    /**
     * Ensure the Datacurso webservice is fully configured when required by the service.
     *
     * @param string|null $serviceid
     * @return void
     */
    private function enforce_webservice_requirements(?string $serviceid): void {
        if (empty($serviceid) || !in_array($serviceid, self::SERVICES_REQUIRING_WEBSERVICE, true)) {
            return;
        }

        if (!\aiprovider_datacurso\webservice_config::is_configured()) {
            $setupurl = \aiprovider_datacurso\webservice_config::get_url();
            $messageparams = (object)['url' => $setupurl->out(false)];
            throw new \moodle_exception('error_webservice_not_configured', 'aiprovider_datacurso', '', $messageparams);
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
