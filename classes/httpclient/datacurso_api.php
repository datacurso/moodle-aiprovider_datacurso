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

use aiprovider_datacurso\local\request_audit;
use aiprovider_datacurso\local\transport_policy;
use moodle_exception;
use moodle_url;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/filelib.php');

/**
 * HTTP client for Tokens Manager API.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datacurso_api {
    /** @var string */
    private $baseurl;

    /** @var string */
    private $licensekey;

    /**
     * Builder.
     *
     * A missing license key is refused when a request is made, so the refusal is recorded in the
     * audit log with the endpoint it was meant for.
     */
    public function __construct() {
        $this->baseurl    = 'https://shop.datacurso.com/index.php?m=tokens_manager&api=';
        $this->licensekey = get_config('aiprovider_datacurso', 'licensekey');
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
     * Build the full URL depending on whether the baseurl uses querystring (?api=).
     *
     * @param string $endpoint The API endpoint.
     * @param array $params Extra query parameters.
     * @return string
     */
    private function build_url(string $endpoint, array $params = []): string {
        $url = rtrim($this->baseurl, '/') . '/' . ltrim($endpoint, '/');
        $url = new moodle_url($url, $params);
        return $url->out(false);
    }

    /**
     * GET request.
     *
     * @param string $endpoint API endpoint.
     * @param array $params Query parameters.
     * @return array
     * @throws moodle_exception
     */
    public function get(string $endpoint, array $params = []): array {
        $url = $this->build_url($endpoint, $params);
        $headers = $this->default_headers();
        return $this->curl_request($endpoint, $url, 'GET', null, $headers);
    }

    /**
     * POST request.
     *
     * @param string $endpoint API endpoint.
     * @param array $data Data to send.
     * @return array
     * @throws moodle_exception
     */
    public function post(string $endpoint, array $data = []): array {
        $url = $this->build_url($endpoint);
        $headers = $this->default_headers(true);
        return $this->curl_request($endpoint, $url, 'POST', $data, $headers);
    }

    /**
     * Default headers (GET/POST).
     *
     * @param bool $ispost Whether it is a POST request.
     * @return array
     */
    private function default_headers(bool $ispost = false): array {
        $headers = [
            "License-Key: {$this->licensekey}",
        ];

        if ($ispost) {
            $headers[] = 'Content-Type: application/json';
        }

        return $headers;
    }

    /**
     * Execute the HTTP request and record it, with its outcome, in the audit log.
     *
     * @param string $endpoint The API endpoint, logged without its parameters.
     * @param string $url The request URL.
     * @param string $method The request method (GET/POST).
     * @param array|null $data Data to send (only for POST).
     * @param array $headers Request headers.
     * @return array
     * @throws moodle_exception invalidlicensekey or insecuretransport when nothing is sent, or the request error.
     */
    private function curl_request(string $endpoint, string $url, string $method, ?array $data, array $headers): array {
        $audit = new request_audit(request_audit::CLIENT_SHOP, 'aiprovider_datacurso', $method, $endpoint);
        try {
            if (empty($this->licensekey)) {
                throw new moodle_exception('invalidlicensekey', 'aiprovider_datacurso');
            }
            transport_policy::require_secure_url($url);
        } catch (moodle_exception $e) {
            $audit->refused($e);
            throw $e;
        }

        $httpstatus = 0;
        try {
            $decoded = $this->perform_curl_request($url, $method, $data, $headers, $httpstatus);
        } catch (\Throwable $e) {
            $audit->failed($e, $httpstatus);
            throw $e;
        }
        $audit->succeeded($httpstatus);
        return $decoded;
    }

    /**
     * Execute the HTTP request using Moodle's \curl wrapper.
     *
     * @param string $url The request URL.
     * @param string $method The request method (GET/POST).
     * @param array|null $data Data to send (only for POST).
     * @param array $headers Request headers.
     * @param int $httpstatus Set to the HTTP status of the answer, 0 while there is none.
     * @return array
     * @throws moodle_exception
     */
    private function perform_curl_request(string $url, string $method, ?array $data, array $headers, int &$httpstatus): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = $this->create_curl();
        $options = transport_policy::curl_options(transport_policy::SHOP_TIMEOUT) + [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        if ($method === 'POST') {
            $response = $curl->post($url, json_encode($data), $options);
        } else {
            $response = $curl->get($url, [], $options);
        }

        // Handle transport and HTTP errors. Exception messages are localized and never include the
        // request URL, the response body or the transport error text (which may contain hostnames);
        // those details are only made available to developers via debugging().
        $info = $curl->get_info();
        $httpcode = (int)($info['http_code'] ?? 0);
        $httpstatus = $httpcode;

        if ($curl->error) {
            debugging(
                "Datacurso shop API {$method} request failed: cURL errno {$curl->errno} ({$curl->error})",
                DEBUG_DEVELOPER
            );
            throw new moodle_exception('curlerror', 'aiprovider_datacurso', '', (int)$curl->errno);
        }

        if (transport_policy::is_redirect($httpcode)) {
            // A redirect is never followed: it would hand the license key to another host.
            debugging("Datacurso shop API {$method} request answered redirect {$httpcode}, not followed", DEBUG_DEVELOPER);
            throw new moodle_exception('httperror', 'aiprovider_datacurso', '', $httpcode);
        }

        if ($httpcode >= 400) {
            debugging(
                "Datacurso shop API {$method} request returned HTTP {$httpcode} (" . strlen((string)$response) . ' bytes)',
                DEBUG_DEVELOPER
            );
            throw new moodle_exception('httperror', 'aiprovider_datacurso', '', $httpcode);
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            debugging(
                "Datacurso shop API {$method} request returned invalid JSON: " . json_last_error_msg()
                    . ' (' . strlen((string)$response) . ' bytes)',
                DEBUG_DEVELOPER
            );
            throw new moodle_exception('jsondecodeerror', 'aiprovider_datacurso', '', json_last_error_msg());
        }

        return $decoded;
    }
}
