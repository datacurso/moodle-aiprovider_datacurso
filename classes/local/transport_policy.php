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

namespace aiprovider_datacurso\local;

use GuzzleHttp\RequestOptions;

/**
 * Transport rules shared by every request the plugin sends to a Datacurso service.
 *
 * The requests carry the license key and the content of the users, so they only travel over
 * HTTPS with the certificate chain and the host name verified, a redirect answer is never
 * followed (it would hand the key to another host), and every request gives up after a total
 * timeout. Moodle's \curl forces its own CURLOPT_PROTOCOLS, so the scheme is checked here before
 * the request is sent instead.
 *
 * Developers who run the services locally over plain HTTP can allow it with
 * `$CFG->aiprovider_datacurso_allow_insecure_transport = true;` in config.php. It is off unless
 * set there, and it only allows the http scheme: certificates are still verified on HTTPS and
 * redirects are still refused.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transport_policy {
    /** @var int Seconds allowed to open the connection. */
    public const CONNECT_TIMEOUT = 30;

    /** @var int Seconds allowed for a whole request to the AI services, generous for long generations. */
    public const AI_SERVICES_TIMEOUT = 300;

    /** @var int Seconds allowed for a whole file download from the AI services. */
    public const DOWNLOAD_TIMEOUT = 300;

    /** @var int Seconds allowed for a whole request to the shop. */
    public const SHOP_TIMEOUT = 30;

    /**
     * Whether config.php allows plain HTTP for local development.
     *
     * @return bool
     */
    public static function allows_insecure_transport(): bool {
        global $CFG;
        return !empty($CFG->aiprovider_datacurso_allow_insecure_transport);
    }

    /**
     * Refuse a URL that would not travel over HTTPS.
     *
     * @param string $url Absolute URL of the request.
     * @throws \moodle_exception insecuretransport when the scheme is not https (or http with the developer flag).
     */
    public static function require_secure_url(string $url): void {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme === 'https' || ($scheme === 'http' && self::allows_insecure_transport())) {
            return;
        }
        debugging('Datacurso request refused: only https URLs are allowed', DEBUG_DEVELOPER);
        throw new \moodle_exception('insecuretransport', 'aiprovider_datacurso');
    }

    /**
     * Options for Moodle's \curl wrapper.
     *
     * @param int $timeout Seconds allowed for the whole request.
     * @return array<string, mixed>
     */
    public static function curl_options(int $timeout): array {
        return [
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_CONNECTTIMEOUT' => self::CONNECT_TIMEOUT,
            'CURLOPT_TIMEOUT' => $timeout,
        ];
    }

    /**
     * Options for core\http_client (Guzzle).
     *
     * @param int $timeout Seconds allowed for the whole request.
     * @return array<string, mixed>
     */
    public static function guzzle_options(int $timeout): array {
        return [
            RequestOptions::VERIFY => true,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => self::CONNECT_TIMEOUT,
            RequestOptions::TIMEOUT => $timeout,
        ];
    }

    /**
     * Whether an HTTP status is a redirect, which the clients report as a failure instead of following.
     *
     * @param int $httpstatus HTTP status of the answer.
     * @return bool
     */
    public static function is_redirect(int $httpstatus): bool {
        return $httpstatus >= 300 && $httpstatus < 400;
    }
}
