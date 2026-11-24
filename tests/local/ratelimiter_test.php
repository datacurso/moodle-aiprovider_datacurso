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

    /**
     * Each Jedomi route resolves to the local_jedomi service and the action it is billed as.
     *
     * Decision 10: Jedomi tells the provider the action of a call by the route it calls; a route that is not a
     * billed action (the free text extraction, or a future one) resolves to 'default'.
     */
    public function test_jedomi_routes_resolve_to_their_actions(): void {
        $expected = [
            '/jedomi/v1/chat/completions' => 'chat',
            '/jedomi/v1/audio/speech' => 'speech',
            '/jedomi/v1/audio/transcriptions' => 'transcription',
            '/jedomi/v1/files/read' => 'file_read',
            'jedomi/v1/files/read' => 'file_read',
            '/jedomi/v1/files/extract' => 'default',
            '/jedomi/v1/unknown' => 'default',
        ];

        foreach ($expected as $path => $actionkey) {
            $serviceid = ratelimiter::resolve_service_for_path($path);
            $this->assertSame('local_jedomi', $serviceid, $path);
            $this->assertSame($actionkey, ratelimiter::resolve_action_key($serviceid, $path), $path);
            $this->assertSame($actionkey, ratelimiter::resolve_action_key($serviceid, $path, ['model' => 'x']), $path);
        }
    }

    /**
     * The look-ahead header of a Jedomi call carries the credits of its action.
     */
    public function test_jedomi_headers_carry_the_credits_of_each_action(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_jedomi_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_jedomi_limit', 40, 'aiprovider_datacurso');
        set_config('ratelimit_local_jedomi_window', json_encode(['value' => 1, 'unit' => 'hours']), 'aiprovider_datacurso');
        set_config(
            'ratelimit_local_jedomi_creditperaction',
            json_encode(['chat' => 3, 'speech' => 1, 'transcription' => 2, 'file_read' => 4]),
            'aiprovider_datacurso'
        );

        $rl = new ratelimiter();
        $expected = [
            '/jedomi/v1/chat/completions' => '3',
            '/jedomi/v1/audio/speech' => '1',
            '/jedomi/v1/audio/transcriptions' => '2',
            '/jedomi/v1/files/read' => '4',
        ];
        foreach ($expected as $path => $credits) {
            $actionkey = ratelimiter::resolve_action_key('local_jedomi', $path);
            $map = $rl->get_rate_limit_header_map('local_jedomi', $actionkey);
            $this->assertSame($credits, $map['X-RateLimit-MaxPerAction'], $path);
        }
    }

    /**
     * Splitting the Jedomi actions leaves the action keys of the other services unchanged.
     */
    public function test_existing_action_keys_are_unchanged(): void {
        $this->assertSame('course_image', ratelimiter::resolve_action_key(
            'local_coursegen',
            '/course/execute',
            ['generate_images' => 1]
        ));
        $this->assertSame('activity_noimage', ratelimiter::resolve_action_key('local_coursegen', '/resources/create-mod'));
        $this->assertSame('activity_image', ratelimiter::resolve_action_key(
            'local_coursedynamicrules',
            '/smartrules/create-mod',
            ['generate_images' => true]
        ));
        $this->assertSame('image', ratelimiter::resolve_action_key('aiprovider_datacurso', '/provider/images/generate'));
        $this->assertSame('text', ratelimiter::resolve_action_key('aiprovider_datacurso', '/provider/chat/completions'));
        $this->assertSame('default', ratelimiter::resolve_action_key('local_dttutor', '/chat/message'));
        $this->assertSame('default', ratelimiter::resolve_action_key(null, '/unknown/path'));
    }

    /**
     * With Jedomi's limit enabled the headers carry its 2-credit look-ahead; disabled sends none.
     *
     * J5: Jedomi rate-limit headers.
     */
    public function test_jedomi_headers_follow_its_limit_configuration(): void {
        $this->resetAfterTest();

        $rl = new ratelimiter();
        $this->assertSame([], $rl->get_rate_limit_header_map('local_jedomi', 'chat'));

        set_config('ratelimit_local_jedomi_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_jedomi_limit', 40, 'aiprovider_datacurso');
        set_config(
            'ratelimit_local_jedomi_window',
            json_encode(['value' => 1, 'unit' => 'hours']),
            'aiprovider_datacurso'
        );

        $map = $rl->get_rate_limit_header_map('local_jedomi', 'chat');

        $this->assertSame('1', $map['X-RateLimit-Enable']);
        $this->assertSame('40', $map['X-RateLimit-Limit']);
        $this->assertSame('3600', $map['X-RateLimit-WindowSeconds']);
        $this->assertSame('2', $map['X-RateLimit-MaxPerAction']);

        set_config('ratelimit_local_jedomi_enable', 0, 'aiprovider_datacurso');

        $this->assertSame([], $rl->get_rate_limit_header_map('local_jedomi', 'chat'));
    }

    /**
     * Adding the Jedomi prefix leaves every existing path mapping untouched.
     *
     * J7: no regression on the existing path mappings.
     */
    public function test_existing_path_mappings_are_unchanged(): void {
        $expected = [
            '/course/execute' => 'local_coursegen',
            '/resources/create-mod' => 'local_coursegen',
            '/context/upload' => 'local_coursegen',
            '/assign/answer' => 'local_assign_ai',
            '/forum/chat' => 'local_forum_ai',
            '/rating/general' => 'local_datacurso_ratings',
            '/certificate/answer' => 'local_socialcert',
            '/story/analysis' => 'report_lifestory',
            '/smartrules/create-mod' => 'local_coursedynamicrules',
            '/provider/chat/completions' => 'aiprovider_datacurso',
            '/chat/message' => 'local_dttutor',
            '/unknown/path' => null,
        ];

        foreach ($expected as $path => $serviceid) {
            $this->assertSame($serviceid, ratelimiter::resolve_service_for_path($path), $path);
        }
    }

    /**
     * Every window unit the tenant settings form offers maps to its length in seconds.
     *
     * Months and years used to fall back to one hour, so a monthly limit was enforced per hour.
     *
     * @dataProvider window_unit_provider
     * @param string $unit Window unit saved by the settings form.
     * @param int $expected Expected length of a window of 2 units, in seconds.
     */
    public function test_window_units_map_to_their_length(string $unit, int $expected): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_coursegen_enable', 1, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_limit', 100, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_window_value', 2, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_window_unit', $unit, 'aiprovider_datacurso');

        $map = (new ratelimiter())->get_rate_limit_header_map('local_coursegen', 'course_image');
        $this->assertSame((string) $expected, $map['X-RateLimit-WindowSeconds']);

        // The legacy JSON window maps the same units.
        unset_config('ratelimit_local_coursegen_window_value', 'aiprovider_datacurso');
        unset_config('ratelimit_local_coursegen_window_unit', 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_window', json_encode(['value' => 2, 'unit' => $unit]), 'aiprovider_datacurso');

        $map = (new ratelimiter())->get_rate_limit_header_map('local_coursegen', 'course_image');
        $this->assertSame((string) $expected, $map['X-RateLimit-WindowSeconds']);
    }

    /**
     * Window units offered by the settings form and the length of 2 of them.
     *
     * @return array
     */
    public static function window_unit_provider(): array {
        return [
            'seconds' => ['seconds', 2],
            'minutes' => ['minutes', 2 * MINSECS],
            'hours' => ['hours', 2 * HOURSECS],
            'days' => ['days', 2 * DAYSECS],
            'months' => ['months', 2 * 30 * DAYSECS],
            'years' => ['years', 2 * 365 * DAYSECS],
        ];
    }
}
