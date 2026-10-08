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

use aiprovider_datacurso\local\service\consumption_service;
use aiprovider_datacurso\local\service\user_service;

/**
 * The consumption history is read per tenant.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\service\consumption_service
 * @covers     \aiprovider_datacurso\local\service\user_service
 */
final class consumption_tenant_test extends \advanced_testcase {
    /**
     * Store a consumption record for a tenant.
     *
     * @param int $externalid
     * @param int $tenantid
     * @param float $credits
     */
    private function consumption(int $externalid, int $tenantid, float $credits): void {
        global $DB;
        $DB->insert_record('aiprovider_datacurso_consumption', (object)[
            'externalid' => $externalid,
            'userid' => 0,
            'tenant_id' => $tenantid,
            'licence' => '',
            'service' => 'local_coursegen',
            'action' => '/course/execute',
            'credits' => $credits,
            'balance' => 0,
            'timecreated' => mktime(12, 0, 0, 3, 10, 2026),
        ]);
    }

    /**
     * The charts add up the consumption of the tenant of the viewer only.
     */
    public function test_the_summary_counts_the_tenant_of_the_viewer_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $mine = tenant_resolver::get_tenant_id();

        $this->consumption(1, $mine, 10);
        $this->consumption(2, $mine + 1000, 99);

        $summary = consumption_service::get_summary('service');

        $this->assertEquals(10, $summary['total']);
    }

    /**
     * On Workplace the user filter only offers the users of the tenant of the viewer.
     */
    public function test_the_user_filter_offers_the_users_of_the_tenant_only(): void {
        // Before anything is created, so a skipped test leaves nothing behind.
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int)$tenants->create_tenant()->id;
        $tenantb = (int)$tenants->create_tenant()->id;
        $viewer = $this->getDataGenerator()->create_user(['firstname' => 'Viewer', 'lastname' => 'Alpha']);
        $colleague = $this->getDataGenerator()->create_user(['firstname' => 'Colleague', 'lastname' => 'Alpha']);
        $stranger = $this->getDataGenerator()->create_user(['firstname' => 'Stranger', 'lastname' => 'Beta']);
        $tenants->allocate_user((int)$viewer->id, $tenanta);
        $tenants->allocate_user((int)$colleague->id, $tenanta);
        $tenants->allocate_user((int)$stranger->id, $tenantb);
        $this->setUser($viewer);

        $ids = array_column(user_service::get_users('')['users'], 'id');

        $this->assertContains((int)$colleague->id, array_map('intval', $ids));
        $this->assertNotContains((int)$stranger->id, array_map('intval', $ids));
    }
}
