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
final class provider_test extends \advanced_testcase {
    /**
     * Ensure provider no longer exposes legacy user-mapping API.
     *
     * @covers \aiprovider_datacurso\provider
     */
    public function test_provider_has_no_ratelimit_settings_mapping_method(): void {
        $this->assertFalse(method_exists(provider::class, 'get_ratelimit_settings_class'));
    }

    /**
     * Ensure provider services list still includes known AI consumers.
     *
     * @covers \aiprovider_datacurso\provider::get_services
     */
    public function test_get_services_contains_known_ids(): void {
        $serviceids = array_column(provider::get_services(), 'id');

        $this->assertContains('local_coursegen', $serviceids);
        $this->assertContains('local_assign_ai', $serviceids);
        $this->assertContains('report_lifestory', $serviceids);
    }

    /**
     * Jedomi is registered as a provider service and its name resolves to the product name.
     *
     * J1: Jedomi service registration.
     *
     * @covers \aiprovider_datacurso\provider::get_services
     */
    public function test_get_services_includes_jedomi(): void {
        $servicemap = array_column(provider::get_services(), 'name', 'id');

        $this->assertArrayHasKey('local_jedomi', $servicemap);
        $this->assertSame('Jedomi', $servicemap['local_jedomi']);
    }

    /**
     * Jedomi exposes a single 'default' action costing 2 credits, which an admin override replaces.
     *
     * J2: Jedomi credits-per-action catalog and override.
     *
     * @covers \aiprovider_datacurso\provider::get_actions_for_service
     * @covers \aiprovider_datacurso\provider::get_credit_for_action
     */
    public function test_jedomi_has_a_single_default_action_of_two_credits(): void {
        $this->resetAfterTest();

        $actions = provider::get_actions_for_service('local_jedomi');

        $this->assertCount(1, $actions);
        $this->assertSame('default', $actions[0]['key']);
        $this->assertSame(2, $actions[0]['default']);
        $this->assertArrayHasKey('local_jedomi', provider::get_service_actions());
        $this->assertSame(2, provider::get_credit_for_action('local_jedomi'));

        set_config('ratelimit_local_jedomi_creditperaction', json_encode(['default' => 7]), 'aiprovider_datacurso');

        $this->assertSame(7, provider::get_credit_for_action('local_jedomi'));
    }

    /**
     * The Jedomi chat endpoint is a known action with a readable label.
     *
     * J3: Jedomi action label for the consumption history.
     *
     * @covers \aiprovider_datacurso\provider::get_actions
     */
    public function test_get_actions_includes_jedomi_chat(): void {
        $actionmap = array_column(provider::get_actions(), 'name', 'id');

        $this->assertArrayHasKey('/jedomi/v1/chat/completions', $actionmap);
        $this->assertSame(
            get_string('generate_jedomi_chat', 'aiprovider_datacurso'),
            $actionmap['/jedomi/v1/chat/completions']
        );
    }

    /**
     * Registering Jedomi leaves the other services and their action catalogs untouched.
     *
     * J7: no regression on the existing services.
     *
     * @covers \aiprovider_datacurso\provider::get_services
     * @covers \aiprovider_datacurso\provider::get_service_actions
     */
    public function test_existing_services_and_catalogs_are_unchanged(): void {
        $serviceids = array_column(provider::get_services(), 'id');
        $this->assertSame([
            'local_coursegen',
            'local_datacurso_ratings',
            'local_forum_ai',
            'local_assign_ai',
            'aiprovider_datacurso',
            'local_dttutor',
            'local_socialcert',
            'report_lifestory',
            'local_coursedynamicrules',
        ], array_values(array_diff($serviceids, ['local_jedomi'])));

        $catalog = [];
        foreach (provider::get_service_actions() as $serviceid => $actions) {
            if ($serviceid === 'local_jedomi') {
                continue;
            }
            $catalog[$serviceid] = array_column($actions, 'default', 'key');
        }
        $this->assertSame([
            'local_coursegen' => [
                'course_image' => 2000,
                'course_noimage' => 1000,
                'activity_image' => 100,
                'activity_noimage' => 50,
            ],
            'aiprovider_datacurso' => ['text' => 1, 'image' => 30],
            'local_coursedynamicrules' => ['activity_image' => 100, 'activity_noimage' => 50],
            'local_datacurso_ratings' => ['default' => 1],
            'local_socialcert' => ['default' => 1],
            'local_forum_ai' => ['default' => 3],
            'report_lifestory' => ['default' => 5],
            'local_assign_ai' => ['default' => 3],
            'local_dttutor' => ['default' => 2],
        ], $catalog);
    }
}
