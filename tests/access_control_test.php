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

use aiprovider_datacurso\form\settings_tenant_form;
use aiprovider_datacurso\local\provider_status;
use aiprovider_datacurso\local\tenant_config;
use aiprovider_datacurso\local\tenant_resolver;

/**
 * Tests for the separation between viewing the reports and managing the configuration (AIP-SEC-003).
 *
 * On Moodle Workplace the configuration is kept per tenant: whoever may manage it changes the configuration
 * of their own tenant only.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\settings_tenant_form
 */
final class access_control_test extends \advanced_testcase {
    /** @var string Plugin whose configuration is checked. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Create a user holding exactly the given capabilities in the system context.
     *
     * @param string[] $capabilities Capabilities to allow.
     * @return \stdClass
     */
    private function user_with(array $capabilities): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, $context->id, true);
        }
        role_assign($roleid, $user->id, $context->id);
        return $user;
    }

    /**
     * The write capability is declared separately from the read one, flagged as a
     * configuration risk and granted to no archetype by default.
     *
     * AIP-SEC-003: write capability for the configuration page.
     */
    public function test_manageconfig_capability_is_declared(): void {
        global $CFG;
        $this->resetAfterTest();

        $capabilities = [];
        require($CFG->dirroot . '/ai/provider/datacurso/db/access.php');

        $this->assertArrayHasKey('aiprovider/datacurso:manageconfig', $capabilities);
        $definition = $capabilities['aiprovider/datacurso:manageconfig'];
        $this->assertSame('write', $definition['captype']);
        $this->assertSame(CONTEXT_SYSTEM, $definition['contextlevel']);
        $this->assertSame(RISK_CONFIG, $definition['riskbitmask']);
        $this->assertEmpty($definition['archetypes'] ?? []);

        // The read capability is untouched.
        $this->assertSame('read', $capabilities['aiprovider/datacurso:viewreports']['captype']);
        $this->assertSame(CAP_ALLOW, $capabilities['aiprovider/datacurso:viewreports']['archetypes']['manager']);

        // And it is installed, with its name string.
        $this->assertNotNull(get_capability_info('aiprovider/datacurso:manageconfig'));
        $this->assertTrue(get_string_manager()->string_exists('datacurso:manageconfig', self::PLUGIN));
    }

    /**
     * A user who can only view the reports cannot reach the configuration form.
     *
     * AIP-SEC-003: reports-only users do not get the form.
     */
    public function test_viewreports_only_user_cannot_manage_config(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_with(['aiprovider/datacurso:viewreports']));

        $this->assertTrue(has_capability('aiprovider/datacurso:viewreports', \context_system::instance()));
        $this->assertFalse(settings_tenant_form::can_manage());

        $this->expectException(\required_capability_exception::class);
        settings_tenant_form::require_manage_capability();
    }

    /**
     * A user who can only view the reports cannot save the configuration, and nothing is written.
     *
     * AIP-SEC-003: the save path is guarded, not only the form rendering.
     */
    public function test_viewreports_only_user_cannot_save(): void {
        $this->resetAfterTest();
        $user = $this->user_with(['aiprovider/datacurso:viewreports']);
        $this->setUser($user);
        $tenantid = tenant_resolver::get_tenant_id((int) $user->id);
        tenant_config::set(self::PLUGIN, $tenantid, 'licensekey', 'ORIGINAL');

        try {
            settings_tenant_form::save((object) ['licensekey' => 'CHANGED', 'provider_enabled' => 0]);
            $this->fail('Expected a required_capability_exception.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('ORIGINAL', tenant_config::get_stored(self::PLUGIN, $tenantid, 'licensekey'));
            $this->assertNull(tenant_config::get_stored(self::PLUGIN, $tenantid, provider_status::CONFIG_NAME));
        }
    }

    /**
     * A user holding the write capability can save the configuration of their tenant.
     *
     * AIP-SEC-003: manageconfig grants the write path.
     */
    public function test_manageconfig_user_can_save(): void {
        $this->resetAfterTest();
        $user = $this->user_with(['aiprovider/datacurso:viewreports', 'aiprovider/datacurso:manageconfig']);
        $this->setUser($user);
        $tenantid = tenant_resolver::get_tenant_id((int) $user->id);

        $this->assertTrue(settings_tenant_form::can_manage());
        $this->assertSame($tenantid, settings_tenant_form::save((object) ['licensekey' => 'NEW-KEY']));

        $this->assertSame('NEW-KEY', tenant_config::get_stored(self::PLUGIN, $tenantid, 'licensekey'));
    }

    /**
     * Site administrators keep the access they had through moodle/site:config.
     */
    public function test_site_administrator_can_save(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(settings_tenant_form::can_manage());
        $tenantid = settings_tenant_form::save((object) ['licensekey' => 'ADMIN-KEY']);

        $this->assertSame('ADMIN-KEY', tenant_config::get_stored(self::PLUGIN, $tenantid, 'licensekey'));
    }

    /**
     * Saving the "Enabled" field switches the provider for the tenant of the user, and logs it.
     */
    public function test_save_switches_the_provider_for_the_tenant(): void {
        global $DB;
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        $this->setAdminUser();

        $tenantid = settings_tenant_form::save((object) ['licensekey' => 'KEY', 'provider_enabled' => 0]);

        $this->assertFalse(provider_status::is_enabled($tenantid));
        $logged = $DB->get_records('config_log', [
            'plugin' => self::PLUGIN,
            'name' => provider_status::CONFIG_NAME . " (tenant {$tenantid})",
        ]);
        $this->assertSame(['0'], array_values(array_column($logged, 'value')));

        settings_tenant_form::save((object) ['licensekey' => 'KEY', 'provider_enabled' => 1]);
        $this->assertTrue(provider_status::is_enabled($tenantid));
    }

    /**
     * On Workplace a user who manages the configuration changes their own tenant only.
     *
     * AIP-SEC-003: the write path is tenant-aware.
     */
    public function test_manageconfig_user_changes_only_their_own_tenant(): void {
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        $this->resetAfterTest();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int) $tenants->create_tenant()->id;
        $tenantb = (int) $tenants->create_tenant()->id;
        $user = $this->user_with(['aiprovider/datacurso:manageconfig']);
        $tenants->allocate_user((int) $user->id, $tenanta);
        tenant_config::set(self::PLUGIN, $tenantb, 'licensekey', 'TENANT-B-KEY');
        $this->setUser($user);

        $this->assertSame($tenanta, settings_tenant_form::save((object) ['licensekey' => 'TENANT-A-KEY', 'provider_enabled' => 0]));

        $this->assertSame('TENANT-A-KEY', tenant_config::get_stored(self::PLUGIN, $tenanta, 'licensekey'));
        $this->assertSame('TENANT-B-KEY', tenant_config::get_stored(self::PLUGIN, $tenantb, 'licensekey'));
        $this->assertFalse(provider_status::is_enabled($tenanta));
        $this->assertTrue(provider_status::is_enabled($tenantb));
    }

    /**
     * The form offers the "Enabled" switch of the tenant, on by default so nothing changes on upgrade.
     */
    public function test_form_offers_the_tenant_switch_enabled_by_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new settings_tenant_form();
        $form->set_data_for_dynamic_submission();
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);

        $this->assertTrue($mform->elementExists(provider_status::CONFIG_NAME));
        $this->assertEquals(1, $mform->getElement(provider_status::CONFIG_NAME)->getValue());
    }
}
