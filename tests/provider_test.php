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
final class provider_test extends \basic_testcase {
    /**
     * Ensure provider no longer exposes legacy user-mapping API.
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
}
