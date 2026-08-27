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

use aiprovider_datacurso\local\upgrade\allowlist_sweeper;

/**
 * Integration coverage for the dead-allowlist-key sweep run by upgrade savepoint
 * 2026082600 (design obs 216/217, "New step 13"). Exercises the extracted helper
 * directly (allowlist_sweeper::run()) rather than the full upgrade path, per the
 * design's own test-design section (unit-level DB seam).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\upgrade\allowlist_sweeper
 */
final class upgrade_step_test extends \advanced_testcase {
    /** @var string[] Dead per-instance allowlist keys that must be stripped. */
    private const DEAD_KEYS = [
        'ratelimit_local_coursegen_allowedusers',
        'ratelimit_local_coursegen_allowedusers_enable',
        'ratelimit_local_coursegen_coursecreators',
        'ratelimit_report_lifestory_activitycreators',
        'ratelimit_report_lifestory_courseanalysts',
        'ratelimit_report_lifestory_generalanalysts',
    ];

    /** @var array Live keys that must survive the sweep untouched. */
    private const LIVE_CONFIG = [
        'licensekey' => 'test-key',
        'ratelimit_local_coursegen_enable' => 1,
        'ratelimit_local_coursegen_limit' => 500,
        'ratelimit_local_coursegen_window_value' => 2,
        'ratelimit_local_coursegen_window_unit' => 'hours',
        'ratelimit_local_coursegen_credit_course_image' => 4321,
    ];

    /**
     * Build the full config array: live keys + all dead keys, each dead key set to '1'.
     *
     * @return array
     */
    private function config_with_dead_keys(): array {
        $config = self::LIVE_CONFIG;
        foreach (self::DEAD_KEYS as $key) {
            $config[$key] = '1';
        }

        return $config;
    }

    /**
     * Seed a Datacurso provider instance with the given config.
     *
     * @param string $name
     * @param array $config
     * @return int instance id
     */
    private function seed_datacurso_instance(string $name, array $config): int {
        global $DB;
        $manager = new \core_ai\manager($DB);
        $instance = $manager->create_provider_instance(
            classname: \aiprovider_datacurso\provider::class,
            name: $name,
            enabled: true,
            config: $config,
        );

        return $instance->id;
    }

    /**
     * Seed a non-Datacurso provider instance (control row) with the same dead-looking keys.
     *
     * @param array $config
     * @return int instance id
     */
    private function seed_other_provider_instance(array $config): int {
        global $DB;
        $manager = new \core_ai\manager($DB);
        $instance = $manager->create_provider_instance(
            classname: \aiprovider_openai\provider::class,
            name: 'openai-control',
            enabled: true,
            config: $config,
        );

        return $instance->id;
    }

    /**
     * The sweep strips only the dead keys, only from aiprovider_datacurso rows, and
     * leaves every live key (including licensekey and the new credit key) untouched.
     */
    public function test_sweep_strips_dead_keys_and_keeps_live_keys(): void {
        global $DB;
        $this->resetAfterTest(true);

        $id1 = $this->seed_datacurso_instance('instance-a', $this->config_with_dead_keys());
        $id2 = $this->seed_datacurso_instance('instance-b', $this->config_with_dead_keys());
        $controlid = $this->seed_other_provider_instance($this->config_with_dead_keys());

        $updated = allowlist_sweeper::run($DB);

        $this->assertSame(2, $updated);

        foreach ([$id1, $id2] as $id) {
            $config = json_decode($DB->get_field('ai_providers', 'config', ['id' => $id]), true);

            foreach (self::DEAD_KEYS as $key) {
                $this->assertArrayNotHasKey($key, $config, "Dead key {$key} survived the sweep on instance {$id}");
            }

            foreach (self::LIVE_CONFIG as $key => $value) {
                $this->assertArrayHasKey($key, $config, "Live key {$key} was removed on instance {$id}");
                $this->assertEquals($value, $config[$key]);
            }
        }

        // Control row (different provider) is untouched: every dead-looking key survives.
        $controlconfig = json_decode($DB->get_field('ai_providers', 'config', ['id' => $controlid]), true);
        foreach (self::DEAD_KEYS as $key) {
            $this->assertArrayHasKey($key, $controlconfig, "Sweep incorrectly touched a non-datacurso instance ({$key})");
        }
    }

    /**
     * A third Datacurso instance whose config carries no dead keys at all is left
     * completely byte-identical (JSON-decoded comparison; the sweep must not even
     * report it as updated).
     */
    public function test_sweep_leaves_clean_instances_byte_identical(): void {
        global $DB;
        $this->resetAfterTest(true);

        $id = $this->seed_datacurso_instance('instance-clean', self::LIVE_CONFIG);
        $before = $DB->get_field('ai_providers', 'config', ['id' => $id]);

        $updated = allowlist_sweeper::run($DB);

        $after = $DB->get_field('ai_providers', 'config', ['id' => $id]);

        $this->assertSame(0, $updated);
        $this->assertSame($before, $after);
    }

    /**
     * Running the sweep twice is idempotent: the second run reports zero updates and
     * the stored config is unchanged by the second pass.
     */
    public function test_sweep_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest(true);

        $id = $this->seed_datacurso_instance('instance-a', $this->config_with_dead_keys());

        $firstrun = allowlist_sweeper::run($DB);
        $this->assertSame(1, $firstrun);

        $afterfirst = $DB->get_field('ai_providers', 'config', ['id' => $id]);

        $secondrun = allowlist_sweeper::run($DB);
        $this->assertSame(0, $secondrun);

        $aftersecond = $DB->get_field('ai_providers', 'config', ['id' => $id]);

        $this->assertSame($afterfirst, $aftersecond);
    }
}
