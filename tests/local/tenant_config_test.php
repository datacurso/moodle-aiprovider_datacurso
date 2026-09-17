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
 * Tests for the per-tenant configuration storage.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\tenant_config
 */
final class tenant_config_test extends \advanced_testcase {
    /**
     * A value set for a tenant is read back for that tenant, without leaking to another one.
     */
    public function test_set_and_get_round_trip_is_tenant_scoped(): void {
        $this->resetAfterTest();

        tenant_config::set('aiprovider_datacurso', 42, 'ratelimit_local_coursegen_limit', 250);

        // Numeric strings round-trip through json_decode() as numbers, not strings: the stored
        // value '250' is valid JSON on its own, so get() decodes it back to int(250).
        $this->assertSame(250, tenant_config::get('aiprovider_datacurso', 42, 'ratelimit_local_coursegen_limit'));
        $this->assertNull(tenant_config::get('aiprovider_datacurso', 7, 'ratelimit_local_coursegen_limit'));
    }

    /**
     * Reading an unset tenant value falls back to the site-wide config, then to the given default.
     */
    public function test_get_falls_back_to_site_config_then_default(): void {
        $this->resetAfterTest();

        $this->assertSame('fallback', tenant_config::get('aiprovider_datacurso', 42, 'licensekey', 'fallback'));

        set_config('licensekey', 'SITE-KEY', 'aiprovider_datacurso');
        $this->assertSame('SITE-KEY', tenant_config::get('aiprovider_datacurso', 42, 'licensekey', 'fallback'));
    }

    /**
     * save_from_form() persists scalar fields but skips the grouped 'credit' structure,
     * which callers must persist explicitly via set() to keep its per-action keys.
     */
    public function test_save_from_form_skips_the_credit_group(): void {
        $this->resetAfterTest();

        tenant_config::save_from_form('aiprovider_datacurso', 42, (object) [
            'licensekey' => 'ABC',
            'credit' => ['local_coursegen' => ['course_image' => 1500]],
            'submitbutton' => 'Save changes',
        ]);

        $this->assertSame('ABC', tenant_config::get('aiprovider_datacurso', 42, 'licensekey'));
        $this->assertNull(tenant_config::get('aiprovider_datacurso', 42, 'credit'));
    }

    /**
     * Setting a value twice for the same tenant updates the existing row instead of duplicating it.
     */
    public function test_set_updates_existing_value(): void {
        $this->resetAfterTest();

        tenant_config::set('aiprovider_datacurso', 42, 'ratelimit_local_coursegen_enable', 0);
        tenant_config::set('aiprovider_datacurso', 42, 'ratelimit_local_coursegen_enable', 1);

        $this->assertSame(1, tenant_config::get('aiprovider_datacurso', 42, 'ratelimit_local_coursegen_enable'));
        $this->assertEquals(1, tenant_config::get_all('aiprovider_datacurso', 42)['ratelimit_local_coursegen_enable']);
    }
}
