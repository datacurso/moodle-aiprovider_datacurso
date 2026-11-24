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

namespace aiprovider_datacurso\form;

/**
 * Tests for the tenant-aware configuration form.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\settings_tenant_form
 */
final class settings_tenant_form_test extends \advanced_testcase {
    /**
     * A negative per-action credit cost is rejected.
     *
     * MDL-UNIT-004: validation of the per-action credit costs.
     */
    public function test_validation_rejects_negative_credit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new settings_tenant_form();

        $errors = $form->validation(
            ['credit' => ['local_coursegen' => ['course_image' => -1]]],
            []
        );

        $this->assertArrayHasKey('credit[local_coursegen][course_image]', $errors);
        $this->assertNotEmpty($errors['credit[local_coursegen][course_image]']);
    }

    /**
     * A valid (non-negative) per-action credit cost passes validation without error.
     *
     * MDL-UNIT-004: valid per-action credit costs produce no validation errors.
     */
    public function test_validation_accepts_non_negative_credit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new settings_tenant_form();

        $errors = $form->validation(
            ['credit' => ['local_coursegen' => ['course_image' => 2000]]],
            []
        );

        $this->assertSame([], $errors);
    }

    /**
     * Credits per action the form prefills for a service, as the tenant settings page shows them.
     *
     * @param string $sid Service id.
     * @return array Credits keyed by action.
     */
    private function prefilled_credits(string $sid): array {
        $form = new settings_tenant_form();
        $data = (new \ReflectionMethod($form, 'get_initial_data'))->invoke($form);
        return $data->credit[$sid];
    }

    /**
     * Create a user in a new tenant and log in as them.
     *
     * @return int Tenant of the user.
     */
    private function login_tenant_user(): int {
        $user = $this->getDataGenerator()->create_user();
        if (\aiprovider_datacurso\local\tenant_resolver::is_tenancy_available()) {
            $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
            $tenants->allocate_user((int) $user->id, (int) $tenants->create_tenant()->id);
        }
        $this->setUser($user);
        return \aiprovider_datacurso\local\tenant_resolver::get_tenant_id((int) $user->id);
    }

    /**
     * The four Jedomi actions are prefilled with a {"default": N} saved before the split into actions.
     *
     * Applies both to a value saved for the tenant and to the site-wide value the tenant inherits.
     *
     * @dataProvider jedomi_legacy_default_provider
     * @param bool $fortenant True to save the legacy value for the tenant, false site-wide.
     */
    public function test_jedomi_actions_prefill_from_a_saved_default(bool $fortenant): void {
        $this->resetAfterTest();
        $tenantid = $this->login_tenant_user();

        $this->assertSame(
            ['chat' => 2, 'speech' => 2, 'transcription' => 2, 'file_read' => 2],
            $this->prefilled_credits('local_jedomi')
        );

        $legacy = json_encode(['default' => 7]);
        if ($fortenant) {
            \aiprovider_datacurso\local\tenant_config::set(
                'aiprovider_datacurso',
                $tenantid,
                'ratelimit_local_jedomi_creditperaction',
                $legacy
            );
        } else {
            set_config('ratelimit_local_jedomi_creditperaction', $legacy, 'aiprovider_datacurso');
        }

        $this->assertSame(
            ['chat' => 7, 'speech' => 7, 'transcription' => 7, 'file_read' => 7],
            $this->prefilled_credits('local_jedomi')
        );
    }

    /**
     * Where the legacy Jedomi value is saved.
     *
     * @return array
     */
    public static function jedomi_legacy_default_provider(): array {
        return [
            'saved for the tenant' => [true],
            'saved site-wide' => [false],
        ];
    }

    /**
     * A saved action value wins over the legacy Jedomi default.
     */
    public function test_jedomi_saved_action_wins_over_the_legacy_default(): void {
        $this->resetAfterTest();
        $tenantid = $this->login_tenant_user();
        \aiprovider_datacurso\local\tenant_config::set(
            'aiprovider_datacurso',
            $tenantid,
            'ratelimit_local_jedomi_creditperaction',
            json_encode(['default' => 7, 'speech' => 3])
        );

        $this->assertSame(
            ['chat' => 7, 'speech' => 3, 'transcription' => 7, 'file_read' => 7],
            $this->prefilled_credits('local_jedomi')
        );
    }

    /**
     * REL-001: a saved {"default": N} does not prefill the actions of a service other than Jedomi.
     */
    public function test_other_service_actions_do_not_prefill_from_a_saved_default(): void {
        $this->resetAfterTest();
        $tenantid = $this->login_tenant_user();
        \aiprovider_datacurso\local\tenant_config::set(
            'aiprovider_datacurso',
            $tenantid,
            'ratelimit_local_coursegen_creditperaction',
            json_encode(['default' => 5])
        );

        $this->assertSame(
            ['course_image' => 2000, 'course_noimage' => 1000, 'activity_image' => 100, 'activity_noimage' => 50],
            $this->prefilled_credits('local_coursegen')
        );
    }
}
