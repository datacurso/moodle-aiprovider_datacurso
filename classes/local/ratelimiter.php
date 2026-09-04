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

use tool_tenant\tenancy;
use aiprovider_datacurso\provider;

/**
 * Per-service rate limit configuration helper.
 *
 * Enforcement happens centrally in the Datacurso Python service (token-manager), which
 * accumulates the real credit consumption per (license, user, service) within the
 * configured window. This class only reads the admin configuration and builds the
 * headers that carry the limit to the service.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ratelimiter {
    /** @var int Current tenant id */
    private $tenantid;

    /**
     * Constructor.
     */
    public function __construct() {
        global $USER;
        $this->tenantid = (int)tenancy::get_tenant_id($USER->id);
    }

    /**
     * Tenant-aware config getter with Moodle global fallback.
     *
     * @param string $key
     * @return mixed
     */
    private function get_tenant_config(string $key) {
        return tenant_config::get('aiprovider_datacurso', $this->tenantid, $key, get_config('aiprovider_datacurso', $key));
    }

    /**
     * Resolve the configured service id from a request path.
     *
     * @param string $path Request path starting with '/'.
     * @return string|null Matching service id or null when unknown.
     */
    public static function resolve_service_for_path(string $path): ?string {
        $normalised = '/' . ltrim($path, '/');
        $map = [
            '/course/' => 'local_coursegen',
            '/resources/' => 'local_coursegen',
            '/context/' => 'local_coursegen',
            '/assign/' => 'local_assign_ai',
            '/forum/' => 'local_forum_ai',
            '/rating/' => 'local_datacurso_ratings',
            '/certificate/' => 'local_socialcert',
            '/story/' => 'report_lifestory',
            '/smartrules/' => 'local_coursedynamicrules',
            '/provider/' => 'aiprovider_datacurso',
            '/chat/' => 'local_dttutor',
        ];

        foreach ($map as $prefix => $service) {
            if (str_starts_with($normalised, $prefix)) {
                return $service;
            }
        }

        return null;
    }

    /**
     * Resolve the sub-action key for a request, from the service, path and request body.
     *
     * The credit-per-action is estimated per sub-action; most services have a single
     * 'default' action, but a few vary by request:
     *  - local_coursegen: course vs activity (by path) x with/without image (by body's
     *    'generate_images' flag) -> course_image|course_noimage|activity_image|activity_noimage.
     *  - local_coursedynamicrules: activity with/without image (by body's 'generate_images').
     * Anything else falls back to 'default'.
     *
     * @param string|null $serviceid Service identifier.
     * @param string $path Request path starting with '/'.
     * @param array $body Decoded request body (associative), when available.
     * @return string Sub-action key (defaults to 'default').
     */
    public static function resolve_action_key(?string $serviceid, string $path, array $body = []): string {
        $normalised = '/' . ltrim($path, '/');
        $withimage = !empty($body['generate_images']);

        switch ($serviceid) {
            case 'local_coursegen':
                $iscourse = str_starts_with($normalised, '/course/');
                if ($iscourse) {
                    return $withimage ? 'course_image' : 'course_noimage';
                }
                return $withimage ? 'activity_image' : 'activity_noimage';
            case 'local_coursedynamicrules':
                return $withimage ? 'activity_image' : 'activity_noimage';
            case 'aiprovider_datacurso':
                // Provider's own actions: image generation vs text/summary, by endpoint path.
                return str_contains($normalised, '/images/') ? 'image' : 'text';
            default:
                return 'default';
        }
    }

    /**
     * Build the rate limit headers (associative map) for the given service.
     *
     * Returns an empty array when the limit is disabled or misconfigured, so callers
     * simply send no rate-limit headers and the service skips windowed enforcement.
     *
     * @param string|null $serviceid Service identifier such as 'local_datacurso_ratings'.
     * @param string|null $actionkey Resolved sub-action key for the look-ahead credit estimate.
     * @return array<string,string> Header name => value.
     */
    public function get_rate_limit_header_map(?string $serviceid, ?string $actionkey = null): array {
        if (empty($serviceid) || !$this->is_rate_limit_enabled($serviceid)) {
            return [];
        }

        $limit = $this->get_service_limit($serviceid);
        $window = $this->get_window_length_in_seconds($serviceid);
        if ($limit <= 0 || $window <= 0) {
            return [];
        }

        $maxperaction = provider::get_credit_for_action($serviceid, $actionkey ?? 'default');

        return [
            'X-RateLimit-Enable' => '1',
            'X-RateLimit-Limit' => (string)$limit,
            'X-RateLimit-WindowSeconds' => (string)$window,
            'X-RateLimit-MaxPerAction' => (string)$maxperaction,
        ];
    }

    /**
     * Build the rate limit headers as cURL-style strings ("Name: value").
     *
     * @param string|null $serviceid Service identifier.
     * @param string|null $actionkey Resolved sub-action key for the look-ahead credit estimate.
     * @return string[] List of header strings, empty when no limit applies.
     */
    public function get_rate_limit_headers(?string $serviceid, ?string $actionkey = null): array {
        $headers = [];
        foreach ($this->get_rate_limit_header_map($serviceid, $actionkey) as $key => $value) {
            $headers[] = $key . ': ' . $value;
        }
        return $headers;
    }

    /**
     * Determine whether the rate limit is enabled for the service.
     *
     * @param string $serviceid Service identifier such as 'local_coursegen'.
     * @return bool True when the rate limit is enabled, false otherwise.
     */
    private function is_rate_limit_enabled(string $serviceid): bool {
        $value = $this->get_tenant_config("ratelimit_{$serviceid}_enable");
        return (int)$value === 1;
    }

    /**
     * Fetch the numeric limit configured for the service.
     *
     * @param string $serviceid
     * @return int
     */
    private function get_service_limit(string $serviceid): int {
        $value = $this->get_tenant_config("ratelimit_{$serviceid}_limit");
        return (int)$value;
    }

    /**
     * Resolve the length of the window in seconds from the stored JSON ({value, unit}).
     *
     * @param string $serviceid
     * @return int
     */
    private function get_window_length_in_seconds(string $serviceid): int {
        $windowvalue = $this->get_tenant_config("ratelimit_{$serviceid}_window_value");
        $windowunit = $this->get_tenant_config("ratelimit_{$serviceid}_window_unit");

        // Fallback to legacy JSON window format.
        if ($windowvalue === false || $windowvalue === null || $windowunit === false || $windowunit === null) {
            $json = (string)$this->get_tenant_config("ratelimit_{$serviceid}_window");
            $data = json_decode($json, true);
            if (!is_array($data)) {
                $data = [];
            }

            $value = (int)($data['value'] ?? 1);
            $value = $value > 0 ? $value : 1;
            $unit = (string)($data['unit'] ?? 'hours');
        } else {
            $value = (int)$windowvalue;
            $value = $value > 0 ? $value : 1;
            $unit = (string)$windowunit;
        }

        $multiplier = match ($unit) {
            'seconds' => 1,
            'minutes' => MINSECS,
            'hours' => HOURSECS,
            'days' => DAYSECS,
            default => HOURSECS,
        };

        return $value * $multiplier;
    }
}
