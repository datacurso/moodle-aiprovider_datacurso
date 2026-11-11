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

use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\tenant_config;
use aiprovider_datacurso\local\tenant_resolver;

/**
 * Keeps the region of a licence resolved, so that building a client does not ask the shop.
 *
 * On Workplace every user belongs to a tenant and the region is kept per tenant, so the answer is
 * stored for the site and for every tenant.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class resolved_region {
    /**
     * Store the standard region for the licence, on the site and on every tenant.
     *
     * @param string $licensekey Licence in force; empty when there is none.
     */
    public static function keep(string $licensekey): void {
        global $DB;

        $values = [
            license_region::REGION => '0',
            license_region::FINGERPRINT => $licensekey === '' ? '' : sha1($licensekey),
            license_region::CHECKED => (string) time(),
        ];
        $tenantids = [];
        if (tenant_resolver::is_tenancy_available()) {
            $tenantids = $DB->get_fieldset_select('tool_tenant', 'id', '1 = 1');
        }
        foreach ($values as $name => $value) {
            set_config($name, $value, 'aiprovider_datacurso');
            foreach ($tenantids as $tenantid) {
                tenant_config::set('aiprovider_datacurso', (int) $tenantid, $name, $value);
            }
        }
    }
}
