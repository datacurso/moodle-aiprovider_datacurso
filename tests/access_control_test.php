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

use aiprovider_datacurso\form\config_form;

/**
 * Tests for the separation between viewing the reports and managing the configuration.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\config_form
 */
final class access_control_test extends \advanced_testcase {
    /**
     * Create a user holding exactly the given capabilities in the system context.
     *
     * @param string[] $capabilities
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
     * AIP-SEC-003: write capability for the configuration tab.
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
        $this->assertTrue(get_string_manager()->string_exists('datacurso:manageconfig', 'aiprovider_datacurso'));
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
        $this->assertFalse(config_form::can_manage());

        $this->expectException(\required_capability_exception::class);
        config_form::require_manage_capability();
    }

    /**
     * A user who can only view the reports cannot save the configuration, and nothing is written.
     *
     * AIP-SEC-003: the save path is guarded, not only the form rendering.
     */
    public function test_viewreports_only_user_cannot_save(): void {
        $this->resetAfterTest();
        set_config('licensekey', 'ORIGINAL', 'aiprovider_datacurso');
        $this->setUser($this->user_with(['aiprovider/datacurso:viewreports']));

        try {
            config_form::save((object) ['licensekey' => 'CHANGED']);
            $this->fail('Expected a required_capability_exception.');
        } catch (\required_capability_exception $e) {
            $this->assertSame('ORIGINAL', get_config('aiprovider_datacurso', 'licensekey'));
        }
    }

    /**
     * A user holding the write capability can save the configuration.
     *
     * AIP-SEC-003: manageconfig grants the write path.
     */
    public function test_manageconfig_user_can_save(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_with(['aiprovider/datacurso:viewreports', 'aiprovider/datacurso:manageconfig']));

        $this->assertTrue(config_form::can_manage());
        config_form::save((object) ['licensekey' => 'NEW-KEY']);

        $this->assertSame('NEW-KEY', get_config('aiprovider_datacurso', 'licensekey'));
    }
}
