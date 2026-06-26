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
     * Build the rate limit headers (associative map) for the given service.
     *
     * Returns an empty array when the limit is disabled or misconfigured, so callers
     * simply send no rate-limit headers and the service skips windowed enforcement.
     *
     * @param string|null $serviceid Service identifier such as 'local_datacurso_ratings'.
     * @return array<string,string> Header name => value.
     */
    public function get_rate_limit_header_map(?string $serviceid): array {
        if (empty($serviceid) || !$this->is_rate_limit_enabled($serviceid)) {
            return [];
        }

        $limit = $this->get_service_limit($serviceid);
        $window = $this->get_window_length_in_seconds($serviceid);
        if ($limit <= 0 || $window <= 0) {
            return [];
        }

        return [
            'X-RateLimit-Enable' => '1',
            'X-RateLimit-Limit' => (string)$limit,
            'X-RateLimit-WindowSeconds' => (string)$window,
        ];
    }

    /**
     * Build the rate limit headers as cURL-style strings ("Name: value").
     *
     * @param string|null $serviceid Service identifier.
     * @return string[] List of header strings, empty when no limit applies.
     */
    public function get_rate_limit_headers(?string $serviceid): array {
        $headers = [];
        foreach ($this->get_rate_limit_header_map($serviceid) as $key => $value) {
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
        return (int)get_config('aiprovider_datacurso', "ratelimit_{$serviceid}_enable") === 1;
    }

    /**
     * Fetch the numeric limit configured for the service.
     *
     * @param string $serviceid
     * @return int
     */
    private function get_service_limit(string $serviceid): int {
        return (int)get_config('aiprovider_datacurso', "ratelimit_{$serviceid}_limit");
    }

    /**
     * Resolve the length of the window in seconds from the stored JSON ({value, unit}).
     *
     * @param string $serviceid
     * @return int
     */
    private function get_window_length_in_seconds(string $serviceid): int {
        $json = (string)get_config('aiprovider_datacurso', "ratelimit_{$serviceid}_window");

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $data = [];
        }

        $value = (int)($data['value'] ?? 1);
        $value = $value > 0 ? $value : 1;

        $unit = (string)($data['unit'] ?? 'hours');
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
