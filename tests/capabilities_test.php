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
 * Tests for the capability definitions of the plugin.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class capabilities_test extends \advanced_testcase {
    /**
     * Viewing the reports shows the consumption of every user by name, so it is flagged as a personal data risk.
     */
    public function test_viewreports_capability_is_flagged_as_personal_risk(): void {
        $capabilities = [];
        require(__DIR__ . '/../db/access.php');

        $this->assertSame(RISK_PERSONAL, $capabilities['aiprovider/datacurso:viewreports']['riskbitmask']);
    }

    /**
     * The installed capability carries the risk too.
     */
    public function test_installed_viewreports_capability_is_flagged_as_personal_risk(): void {
        $capability = get_capability_info('aiprovider/datacurso:viewreports');

        $this->assertNotEmpty($capability);
        $this->assertSame(RISK_PERSONAL, RISK_PERSONAL & (int) $capability->riskbitmask);
    }
}
