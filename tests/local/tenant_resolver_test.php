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
 * Tests for the tenant resolver.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\tenant_resolver
 */
final class tenant_resolver_test extends \advanced_testcase {
    /**
     * A user allocated to a tenant resolves to that tenant.
     */
    public function test_user_resolves_to_its_tenant(): void {
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantid = (int) $tenants->create_tenant()->id;
        $user = $this->getDataGenerator()->create_user();
        $tenants->allocate_user((int) $user->id, $tenantid);

        $this->assertSame($tenantid, tenant_resolver::get_tenant_id((int) $user->id));
    }

    /**
     * During the initial install the tables of tool_tenant may not exist yet, so no tenant is resolved.
     *
     * The settings of other plugins (for example report_lifestory) ask for the tenant of the current user
     * while the site is installed, before tool_tenant has created its tables.
     */
    public function test_no_tenant_is_resolved_during_the_initial_install(): void {
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenantid = (int) $tenants->create_tenant()->id;
        $user = $this->getDataGenerator()->create_user();
        $tenants->allocate_user((int) $user->id, $tenantid);

        set_config('rolesactive', 0);
        $this->assertTrue(during_initial_install());

        $this->assertSame(tenant_resolver::NO_TENANT, tenant_resolver::get_tenant_id((int) $user->id));
    }
}
