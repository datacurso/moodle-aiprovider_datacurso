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

namespace aiprovider_datacurso;

/**
 * Provider tests for Datacurso AI provider.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\provider::class)]
final class provider_test extends \basic_testcase {
    /**
     * Ensure provider no longer exposes legacy user-mapping API.
     */
    public function test_provider_has_no_ratelimit_settings_mapping_method(): void {
        $this->assertFalse(method_exists(provider::class, 'get_ratelimit_settings_class'));
    }

    /**
     * Ensure provider services list still includes known AI consumers.
     */
    public function test_get_services_contains_known_ids(): void {
        $serviceids = array_column(provider::get_services(), 'id');

        $this->assertContains('local_coursegen', $serviceids);
        $this->assertContains('local_assign_ai', $serviceids);
        $this->assertContains('report_lifestory', $serviceids);
    }

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
     * The instance-scoped flat key `ratelimit_{sid}_credit_{actionkey}` wins over the
     * catalogue default when present.
     */
    public function test_get_credit_for_action_reads_instance_config(): void {
        $instance = $this->make_instance([
            'ratelimit_local_coursegen_credit_course_image' => 777,
        ]);

        $this->assertSame(777, $instance->get_credit_for_action('local_coursegen', 'course_image'));
    }

    /**
     * With no instance-config key set, the catalogue default for the action wins.
     */
    public function test_get_credit_for_action_falls_back_to_the_catalogue_default(): void {
        $instance = $this->make_instance([]);

        // Catalogue default for local_coursegen/course_image (provider::get_service_actions()).
        $this->assertSame(2000, $instance->get_credit_for_action('local_coursegen', 'course_image'));
    }

    /**
     * A zero or negative configured value never yields fewer than 1 credit.
     */
    public function test_get_credit_for_action_is_never_below_one(): void {
        $zero = $this->make_instance([
            'ratelimit_local_datacurso_ratings_credit_default' => 0,
        ]);
        $this->assertSame(1, $zero->get_credit_for_action('local_datacurso_ratings', 'default'));

        $negative = $this->make_instance([
            'ratelimit_local_datacurso_ratings_credit_default' => -5,
        ]);
        $this->assertSame(1, $negative->get_credit_for_action('local_datacurso_ratings', 'default'));
    }

    /**
     * get_credit_for_action() must be a public, non-static instance method: it reads
     * $this->config, which is only populated on a constructed provider instance.
     */
    public function test_get_credit_for_action_is_an_instance_method(): void {
        $method = new \ReflectionMethod(provider::class, 'get_credit_for_action');

        $this->assertFalse($method->isStatic());
        $this->assertTrue($method->isPublic());
    }

    /**
     * The default window limit is the most expensive action per catalogued service, else 10.
     *
     * Ported from 4.5's tests/provider_catalog_test.php::test_default_window_limit.
     */
    public function test_default_window_limit_catalogue_values(): void {
        $this->assertSame(30, provider::get_default_window_limit('aiprovider_datacurso'));
        $this->assertSame(2000, provider::get_default_window_limit('local_coursegen'));
        $this->assertSame(10, provider::get_default_window_limit('service_without_catalog'));
    }
}
