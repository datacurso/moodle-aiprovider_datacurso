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

use aiprovider_datacurso\local\tenant_config;
use aiprovider_datacurso\local\tenant_resolver;

/**
 * The site licence key never reaches a tenant manager through the tenant settings form.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\settings_tenant_form
 */
final class settings_tenant_licence_key_test extends \advanced_testcase {
    /** @var string Plugin whose configuration is checked. */
    private const PLUGIN = 'aiprovider_datacurso';

    /** @var string Site licence key that must stay hidden from tenant managers. */
    private const SITE_KEY = 'SITE-SECRET-KEY-123';

    /**
     * Create a tenant manager: a user of a new tenant holding manageconfig, and log in as them.
     *
     * @return int Tenant of the user.
     */
    private function login_tenant_manager(): int {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        assign_capability('aiprovider/datacurso:manageconfig', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);

        if (tenant_resolver::is_tenancy_available()) {
            $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
            $tenants->allocate_user((int) $user->id, (int) $tenants->create_tenant()->id);
        }

        $this->setUser($user);
        return tenant_resolver::get_tenant_id((int) $user->id);
    }

    /**
     * Build the form with its initial data, as the settings page does.
     *
     * @return array Form object and its rendered HTML.
     */
    private function build_form(): array {
        $form = new settings_tenant_form();
        $form->set_data_for_dynamic_submission();
        return [$form, $form->render()];
    }

    /**
     * Value the licence key field of the form holds.
     *
     * @param settings_tenant_form $form
     * @return string
     */
    private function licence_value(settings_tenant_form $form): string {
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);
        return (string) $mform->getElement('licensekey')->getValue();
    }

    /**
     * A tenant manager whose tenant has no key gets an empty field and never the site key.
     */
    public function test_tenant_manager_never_receives_the_site_key(): void {
        $this->resetAfterTest();
        set_config('licensekey', self::SITE_KEY, self::PLUGIN);
        $this->login_tenant_manager();

        [$form, $html] = $this->build_form();

        $this->assertSame('', $this->licence_value($form));
        $this->assertStringNotContainsString(self::SITE_KEY, $html);
        $this->assertStringContainsString(get_string('licensekey_usessitelicence', self::PLUGIN), $html);
    }

    /**
     * Saving the form with an empty key stores nothing, so the tenant keeps inheriting the site key.
     */
    public function test_saving_an_empty_key_stores_nothing(): void {
        $this->resetAfterTest();
        set_config('licensekey', self::SITE_KEY, self::PLUGIN);
        $tenantid = $this->login_tenant_manager();

        settings_tenant_form::save((object) ['licensekey' => '', 'provider_enabled' => 1]);

        $this->assertNull(tenant_config::get_stored(self::PLUGIN, $tenantid, 'licensekey'));
        $this->assertSame(self::SITE_KEY, tenant_config::get(self::PLUGIN, $tenantid, 'licensekey'));
    }

    /**
     * A tenant with its own key still sees it in the form and keeps it when saving.
     */
    public function test_tenant_with_its_own_key_sees_and_keeps_it(): void {
        $this->resetAfterTest();
        set_config('licensekey', self::SITE_KEY, self::PLUGIN);
        $tenantid = $this->login_tenant_manager();
        tenant_config::set(self::PLUGIN, $tenantid, 'licensekey', 'TENANT-OWN-KEY');

        [$form, $html] = $this->build_form();
        $this->assertSame('TENANT-OWN-KEY', $this->licence_value($form));
        $this->assertStringNotContainsString(self::SITE_KEY, $html);

        settings_tenant_form::save((object) ['licensekey' => 'TENANT-OWN-KEY']);
        $this->assertSame('TENANT-OWN-KEY', tenant_config::get_stored(self::PLUGIN, $tenantid, 'licensekey'));
    }

    /**
     * A site administrator still sees the site key, as before.
     */
    public function test_site_administrator_still_sees_the_site_key(): void {
        $this->resetAfterTest();
        set_config('licensekey', self::SITE_KEY, self::PLUGIN);
        $this->setAdminUser();

        [$form] = $this->build_form();

        $this->assertSame(self::SITE_KEY, $this->licence_value($form));
    }
}
