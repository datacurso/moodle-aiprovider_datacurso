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
 * Tests for the per-service rate limit header builder.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\ratelimiter
 */
final class ratelimiter_test extends \advanced_testcase {
    /**
     * An enabled service with a valid limit, window and cost emits the four enforcement headers.
     *
     * MDL-UNIT-001: rate limit header construction (enabled path).
     */
    public function test_headers_are_built_when_enabled_with_valid_values(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_coursegen_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_limit', 100, 'aiprovider_datacurso');
        set_config(
            'ratelimit_local_coursegen_window',
            json_encode(['value' => 2, 'unit' => 'hours']),
            'aiprovider_datacurso'
        );

        $map = (new ratelimiter())->get_rate_limit_header_map('local_coursegen', 'course_image');

        $this->assertSame('1', $map['X-RateLimit-Enable']);
        $this->assertSame('100', $map['X-RateLimit-Limit']);
        // 2 hours => 7200 seconds.
        $this->assertSame('7200', $map['X-RateLimit-WindowSeconds']);
        // The course_image catalog default is 2000; the value is always a positive integer string.
        $this->assertSame('2000', $map['X-RateLimit-MaxPerAction']);
        $this->assertGreaterThanOrEqual(1, (int) $map['X-RateLimit-MaxPerAction']);
    }

    /**
     * A disabled service emits no headers, so no enforcement is requested.
     *
     * MDL-UNIT-001: rate limit header construction (disabled path).
     */
    public function test_no_headers_when_disabled(): void {
        $this->resetAfterTest();

        // Enable flag not set (defaults to disabled).
        set_config('ratelimit_local_coursegen_limit', 100, 'aiprovider_datacurso');

        $this->assertSame([], (new ratelimiter())->get_rate_limit_header_map('local_coursegen', 'course_image'));
    }

    /**
     * A limit of 0 (unlimited) produces no enforcement headers.
     *
     * MDL-UNIT-001: rate limit header construction (limit 0 => no enforcement).
     *
     * Note: the window length is floored to >= 1 in the source
     * ({@see \aiprovider_datacurso\local\ratelimiter::get_window_length_in_seconds}),
     * so a window <= 0 is unreachable through configuration; the "no enforcement"
     * requirement is therefore covered via the disabled and limit-0 branches.
     */
    public function test_no_headers_when_limit_is_zero(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_coursegen_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_limit', 0, 'aiprovider_datacurso');

        $this->assertSame([], (new ratelimiter())->get_rate_limit_header_map('local_coursegen', 'course_image'));
    }

    /**
     * The cURL-style header list mirrors the header map as "Name: value" strings.
     *
     * API-CTR-001: rate limit headers sent to the external service.
     */
    public function test_header_strings_match_the_map(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_coursegen_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_limit', 100, 'aiprovider_datacurso');
        set_config(
            'ratelimit_local_coursegen_window',
            json_encode(['value' => 2, 'unit' => 'hours']),
            'aiprovider_datacurso'
        );

        $rl = new ratelimiter();
        $map = $rl->get_rate_limit_header_map('local_coursegen', 'course_image');
        $headers = $rl->get_rate_limit_headers('local_coursegen', 'course_image');

        $expected = [];
        foreach ($map as $name => $value) {
            $expected[] = $name . ': ' . $value;
        }

        $this->assertSame($expected, $headers);
        $this->assertContains('X-RateLimit-Enable: 1', $headers);
        $this->assertContains('X-RateLimit-Limit: 100', $headers);
        $this->assertContains('X-RateLimit-WindowSeconds: 7200', $headers);
    }

    /**
     * Disabled service yields an empty header string list.
     *
     * API-CTR-001: no rate limit headers when disabled.
     */
    public function test_header_strings_empty_when_disabled(): void {
        $this->resetAfterTest();

        $this->assertSame([], (new ratelimiter())->get_rate_limit_headers('local_coursegen', 'course_image'));
    }
}
