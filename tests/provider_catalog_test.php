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
 * Tests for the provider credit/limit catalog resolution.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\provider
 */
final class provider_catalog_test extends \advanced_testcase {
    /**
     * The default window limit is the most expensive action per catalogued service, else 10.
     *
     * MDL-UNIT-002: default credit limit by the most expensive service action.
     */
    public function test_default_window_limit(): void {
        $this->assertSame(30, provider::get_default_window_limit('aiprovider_datacurso'));
        $this->assertSame(2000, provider::get_default_window_limit('local_coursegen'));
        $this->assertSame(10, provider::get_default_window_limit('service_without_catalog'));
    }

    /**
     * The per-sub-action credit cost is resolved by its key.
     *
     * MDL-UNIT-003: resolution of the credit cost per action.
     */
    public function test_credit_for_known_subactions(): void {
        $this->assertSame(2000, provider::get_credit_for_action('local_coursegen', 'course_image'));
        $this->assertSame(50, provider::get_credit_for_action('local_coursegen', 'activity_noimage'));
    }

    /**
     * An unknown sub-action key falls back to the service 'default' cost, never below 1.
     *
     * MDL-UNIT-003: fallback to the service default cost for unknown keys.
     */
    public function test_credit_for_unknown_subaction_falls_back(): void {
        // The local_forum_ai fixture exposes a single 'default' action with cost 3.
        $cost = provider::get_credit_for_action('local_forum_ai', 'does_not_exist');
        $this->assertSame(3, $cost);
        $this->assertGreaterThanOrEqual(1, $cost);

        // A service with only keyed sub-actions falls back to its first catalogued action.
        $coursegen = provider::get_credit_for_action('local_coursegen', 'does_not_exist');
        $this->assertGreaterThanOrEqual(1, $coursegen);
    }

    /**
     * An admin-saved JSON override wins over the catalog default.
     *
     * MDL-UNIT-003: config override branch of the credit resolution.
     */
    public function test_credit_for_action_uses_admin_override(): void {
        $this->resetAfterTest();

        set_config(
            'ratelimit_local_forum_ai_creditperaction',
            json_encode(['default' => 99]),
            'aiprovider_datacurso'
        );

        $this->assertSame(99, provider::get_credit_for_action('local_forum_ai', 'default'));
    }

    /**
     * A tenant-scoped override wins over the site-wide setting when a tenant id is given.
     *
     * MDL-UNIT-003: tenant-aware branch of the credit resolution.
     */
    public function test_credit_for_action_prefers_tenant_override(): void {
        $this->resetAfterTest();

        set_config('ratelimit_local_forum_ai_creditperaction', json_encode(['default' => 99]), 'aiprovider_datacurso');
        \aiprovider_datacurso\local\tenant_config::set(
            'aiprovider_datacurso',
            42,
            'ratelimit_local_forum_ai_creditperaction',
            json_encode(['default' => 7])
        );

        // Tenant 42 has its own override.
        $this->assertSame(7, provider::get_credit_for_action('local_forum_ai', 'default', 42));

        // A tenant without an override falls back to the site-wide setting.
        $this->assertSame(99, provider::get_credit_for_action('local_forum_ai', 'default', 99));

        // No tenant id given at all keeps reading the site-wide setting directly.
        $this->assertSame(99, provider::get_credit_for_action('local_forum_ai', 'default'));
    }
}
