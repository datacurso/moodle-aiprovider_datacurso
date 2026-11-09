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

use aiprovider_datacurso\provider;

/**
 * Tests for the instance-scoped rate limit header builder.
 *
 * Rate limit values must come from the provider-instance config
 * ($instanceprovider->config), never from global plugin config: enforcement moved
 * to the remote token-manager, this class only computes and forwards headers.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\local\ratelimiter::class)]
final class ratelimiter_test extends \advanced_testcase {
    /**
     * Build a provider instance with the given config, no DB writes.
     *
     * @param array $config
     * @return provider
     */
    private function make_instance(array $config): provider {
        return new provider(
            enabled: true,
            name: 'test',
            config: json_encode($config),
            actionconfig: '{}',
        );
    }

    /**
     * The header map is built entirely from the instance config.
     */
    public function test_header_map_reads_instance_config(): void {
        $instance = $this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_coursegen_enable' => 1,
            'ratelimit_local_coursegen_limit' => 500,
            'ratelimit_local_coursegen_window_value' => 2,
            'ratelimit_local_coursegen_window_unit' => 'hours',
            'ratelimit_local_coursegen_credit_course_image' => 4321,
        ]);

        $ratelimiter = new ratelimiter($instance);
        $headers = $ratelimiter->get_rate_limit_header_map('local_coursegen', 'course_image');

        $this->assertSame('500', $headers['X-RateLimit-Limit']);
        $this->assertSame('7200', $headers['X-RateLimit-WindowSeconds']);
        $this->assertSame('4321', $headers['X-RateLimit-MaxPerAction']);
    }

    /**
     * A disabled service yields no rate limit headers.
     */
    public function test_header_map_is_empty_when_disabled(): void {
        $instance = $this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_coursegen_enable' => 0,
            'ratelimit_local_coursegen_limit' => 500,
        ]);

        $ratelimiter = new ratelimiter($instance);

        $this->assertSame([], $ratelimiter->get_rate_limit_header_map('local_coursegen'));
    }

    /**
     * A path that maps to no service yields no rate limit headers.
     */
    public function test_header_map_is_empty_for_unmapped_path(): void {
        $instance = $this->make_instance(['licensekey' => 'test-key']);

        $ratelimiter = new ratelimiter($instance);

        $this->assertSame([], $ratelimiter->get_rate_limit_header_map(null));
    }

    /**
     * Global (site) config must never influence the instance-scoped headers.
     */
    public function test_header_map_ignores_global_config(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_coursegen_limit', 999, 'aiprovider_datacurso');
        set_config('ratelimit_local_coursegen_enable', 1, 'aiprovider_datacurso');

        $instance = $this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_coursegen_enable' => 1,
            'ratelimit_local_coursegen_limit' => 500,
            'ratelimit_local_coursegen_window_value' => 1,
            'ratelimit_local_coursegen_window_unit' => 'hours',
        ]);

        $ratelimiter = new ratelimiter($instance);
        $headers = $ratelimiter->get_rate_limit_header_map('local_coursegen');

        $this->assertSame('500', $headers['X-RateLimit-Limit']);
    }

    /**
     * Header values are always numeric strings: no CRLF can be injected via config.
     */
    public function test_header_values_are_numeric_strings(): void {
        $instance = $this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_coursegen_enable' => 1,
            'ratelimit_local_coursegen_limit' => "5\r\nX-Injected: 1",
            'ratelimit_local_coursegen_window_value' => 1,
            'ratelimit_local_coursegen_window_unit' => 'hours',
        ]);

        $ratelimiter = new ratelimiter($instance);
        $headers = $ratelimiter->get_rate_limit_header_map('local_coursegen');

        $this->assertSame('5', $headers['X-RateLimit-Limit']);
    }

    /**
     * The constructor requires the instance provider: no default, no lazy lookup.
     */
    public function test_constructor_requires_an_instance_provider(): void {
        $constructor = new \ReflectionMethod(ratelimiter::class, '__construct');
        $params = $constructor->getParameters();

        $this->assertCount(1, $params);
        $this->assertFalse($params[0]->isOptional());
        $this->assertSame(provider::class, $params[0]->getType()->getName());
    }

    /**
     * Jedomi endpoints resolve to the local_jedomi service and its single 'default' action.
     *
     * J4: Jedomi path resolution for the rate-limit headers.
     */
    public function test_jedomi_path_resolves_to_jedomi_service(): void {
        $path = '/jedomi/v1/chat/completions';
        $serviceid = ratelimiter::resolve_service_for_path($path);

        $this->assertSame('local_jedomi', $serviceid);
        $this->assertSame('default', ratelimiter::resolve_action_key($serviceid, $path));
    }

    /**
     * With Jedomi's limit enabled the headers carry its 2-credit look-ahead; disabled sends none.
     *
     * J5: Jedomi rate-limit headers.
     */
    public function test_jedomi_headers_follow_its_limit_configuration(): void {
        $disabled = new ratelimiter($this->make_instance(['licensekey' => 'test-key']));
        $this->assertSame([], $disabled->get_rate_limit_header_map('local_jedomi', 'default'));

        $enabled = new ratelimiter($this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_jedomi_enable' => 1,
            'ratelimit_local_jedomi_limit' => 40,
            'ratelimit_local_jedomi_window_value' => 1,
            'ratelimit_local_jedomi_window_unit' => 'hours',
        ]));

        $map = $enabled->get_rate_limit_header_map('local_jedomi', 'default');

        $this->assertSame('1', $map['X-RateLimit-Enable']);
        $this->assertSame('40', $map['X-RateLimit-Limit']);
        $this->assertSame('3600', $map['X-RateLimit-WindowSeconds']);
        $this->assertSame('2', $map['X-RateLimit-MaxPerAction']);

        $switchedoff = new ratelimiter($this->make_instance([
            'licensekey' => 'test-key',
            'ratelimit_local_jedomi_enable' => 0,
            'ratelimit_local_jedomi_limit' => 40,
        ]));

        $this->assertSame([], $switchedoff->get_rate_limit_header_map('local_jedomi', 'default'));
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
}
