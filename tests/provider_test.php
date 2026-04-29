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
     * Ensure known services map to their explicit ratelimit settings class.
     *
     * @covers \aiprovider_datacurso\provider::get_ratelimit_settings_class
     */
    public function test_get_ratelimit_settings_class_for_known_services(): void {
        $this->assertSame(
            \aiprovider_datacurso\local\ratelimit\local_coursegen::class,
            provider::get_ratelimit_settings_class('local_coursegen')
        );
        $this->assertSame(
            \aiprovider_datacurso\local\ratelimit\local_datacurso_ratings::class,
            provider::get_ratelimit_settings_class('local_datacurso_ratings')
        );
        $this->assertSame(
            \aiprovider_datacurso\local\ratelimit\local_assign_ai::class,
            provider::get_ratelimit_settings_class('local_assign_ai')
        );
        $this->assertSame(
            \aiprovider_datacurso\local\ratelimit\report_lifestory::class,
            provider::get_ratelimit_settings_class('report_lifestory')
        );
    }

    /**
     * Ensure services without extension class do not trigger dynamic lookup.
     *
     * @covers \aiprovider_datacurso\provider::get_ratelimit_settings_class
     */
    public function test_get_ratelimit_settings_class_for_unknown_services(): void {
        $this->assertNull(provider::get_ratelimit_settings_class('local_forum_ai'));
        $this->assertNull(provider::get_ratelimit_settings_class('aiprovider_datacurso'));
    }
}
