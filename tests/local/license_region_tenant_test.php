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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_license_region.php');

/**
 * The region of a licence is resolved once and kept for each tenant.
 *
 * On Workplace each tenant has its own licence, or falls back to the site licence, so the region
 * one tenant resolves must never answer for another.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\license_region
 */
final class license_region_tenant_test extends \advanced_testcase {
    /** @var int A tenant with a licence of the European deployment. */
    private const EUROPEAN_TENANT = 7;

    /** @var int A tenant with a licence of the standard deployment. */
    private const STANDARD_TENANT = 9;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        testable_license_region::reset();
        set_config('licensekey', 'DC-SITE-LICENCE', 'aiprovider_datacurso');
        tenant_config::set('aiprovider_datacurso', self::EUROPEAN_TENANT, 'licensekey', 'DC-EU-LICENCE');
        tenant_config::set('aiprovider_datacurso', self::STANDARD_TENANT, 'licensekey', 'DC-STANDARD-LICENCE');
    }

    /**
     * Each tenant is answered with the region of its own licence, asked once per tenant.
     */
    public function test_each_tenant_keeps_the_region_of_its_own_licence(): void {
        testable_license_region::$european = true;
        $this->assertTrue(testable_license_region::is_european(self::EUROPEAN_TENANT));
        $this->assertSame('DC-EU-LICENCE', testable_license_region::$licence);

        testable_license_region::$european = false;
        $this->assertFalse(testable_license_region::is_european(self::STANDARD_TENANT));
        $this->assertSame('DC-STANDARD-LICENCE', testable_license_region::$licence);

        $this->assertTrue(testable_license_region::is_european(self::EUROPEAN_TENANT));
        $this->assertFalse(testable_license_region::is_european(self::STANDARD_TENANT));
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * The region of a tenant is kept with the tenant, not in the site configuration.
     */
    public function test_the_region_of_a_tenant_is_not_kept_for_the_site(): void {
        testable_license_region::$european = true;

        testable_license_region::is_european(self::EUROPEAN_TENANT);

        $this->assertFalse(get_config('aiprovider_datacurso', license_region::REGION));
        $this->assertSame('1', tenant_config::get_stored('aiprovider_datacurso', self::EUROPEAN_TENANT, license_region::REGION));
        $this->assertSame(
            sha1('DC-EU-LICENCE'),
            tenant_config::get_stored('aiprovider_datacurso', self::EUROPEAN_TENANT, license_region::FINGERPRINT)
        );
    }

    /**
     * A tenant without a licence of its own is answered for the site licence it falls back to.
     */
    public function test_a_tenant_without_a_licence_uses_the_site_licence(): void {
        testable_license_region::$european = true;

        $this->assertTrue(testable_license_region::is_european(11));

        $this->assertSame('DC-SITE-LICENCE', testable_license_region::$licence);
    }

    /**
     * A licence given explicitly is the one the shop is asked about.
     */
    public function test_an_explicit_licence_is_the_one_asked_about(): void {
        testable_license_region::is_european(self::STANDARD_TENANT, 'DC-GIVEN-LICENCE');

        $this->assertSame('DC-GIVEN-LICENCE', testable_license_region::$licence);
    }

    /**
     * Changing the licence of a tenant asks for the region of the new one.
     */
    public function test_changing_the_licence_of_a_tenant_asks_again(): void {
        testable_license_region::is_european(self::STANDARD_TENANT);
        tenant_config::set('aiprovider_datacurso', self::STANDARD_TENANT, 'licensekey', 'DC-NEW-LICENCE');
        testable_license_region::$european = true;

        $this->assertTrue(testable_license_region::is_european(self::STANDARD_TENANT));
        $this->assertSame(2, testable_license_region::$asked);
        $this->assertSame('DC-NEW-LICENCE', testable_license_region::$licence);
    }

    /**
     * Forgetting the region of one tenant leaves the other tenants alone.
     */
    public function test_forgetting_one_tenant_keeps_the_others(): void {
        testable_license_region::is_european(self::EUROPEAN_TENANT);
        testable_license_region::is_european(self::STANDARD_TENANT);

        license_region::forget_for_tenant(self::EUROPEAN_TENANT);

        $this->assertNull(tenant_config::get_stored('aiprovider_datacurso', self::EUROPEAN_TENANT, license_region::REGION));
        $this->assertNotNull(tenant_config::get_stored('aiprovider_datacurso', self::STANDARD_TENANT, license_region::REGION));
    }

    /**
     * Forgetting the region, as saving the site licence does, drops it for the site and every tenant.
     */
    public function test_forgetting_drops_the_site_and_every_tenant(): void {
        testable_license_region::is_european(self::EUROPEAN_TENANT);
        testable_license_region::is_european(self::STANDARD_TENANT);
        testable_license_region::is_european(tenant_resolver::NO_TENANT);

        license_region::forget();

        $this->assertFalse(get_config('aiprovider_datacurso', license_region::REGION));
        $this->assertNull(tenant_config::get_stored('aiprovider_datacurso', self::EUROPEAN_TENANT, license_region::REGION));
        $this->assertNull(tenant_config::get_stored('aiprovider_datacurso', self::STANDARD_TENANT, license_region::REGION));
    }

    /**
     * The settings callback passes the setting name, which forgetting everything must accept.
     */
    public function test_forgetting_accepts_the_settings_callback_argument(): void {
        testable_license_region::is_european(self::EUROPEAN_TENANT);

        call_user_func('aiprovider_datacurso\local\license_region::forget', 's_aiprovider_datacurso_licensekey');

        $this->assertNull(tenant_config::get_stored('aiprovider_datacurso', self::EUROPEAN_TENANT, license_region::REGION));
    }

    /**
     * A known region of a tenant is kept when the shop cannot be reached.
     */
    public function test_a_shop_that_is_down_does_not_stop_a_known_tenant(): void {
        testable_license_region::$european = true;
        testable_license_region::is_european(self::EUROPEAN_TENANT);
        tenant_config::set(
            'aiprovider_datacurso',
            self::EUROPEAN_TENANT,
            license_region::CHECKED,
            time() - license_region::TTL - 1
        );
        testable_license_region::$failure = new \moodle_exception('curlerror', 'aiprovider_datacurso');

        $this->assertTrue(testable_license_region::is_european(self::EUROPEAN_TENANT));
        $this->assertDebuggingCalled();
    }
}
