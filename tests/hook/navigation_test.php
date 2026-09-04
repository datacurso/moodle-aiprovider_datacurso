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

namespace aiprovider_datacurso\hook;

use core\hook\navigation\primary_extend;
use core\navigation\views\primary;

/**
 * Tests for the primary-navigation node capability gate.
 *
 * The report page's gate moved from moodle/site:config to the manager-archetype
 * capability aiprovider/datacurso:viewreports (design's privilege-boundary threat
 * note); the primary-nav node must use the same gate.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\hook\navigation::class)]
final class navigation_test extends \advanced_testcase {
    /**
     * Dispatch primary_extend on a fresh primary navigation view for the current user.
     *
     * @return primary
     */
    private function dispatch(): primary {
        global $PAGE;

        $primaryview = new primary($PAGE);
        $hook = new primary_extend($primaryview);
        navigation::primary_extend($hook);

        return $primaryview;
    }

    /**
     * A user without the capability never sees the node.
     */
    public function test_node_is_hidden_without_the_viewreports_capability(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $primaryview = $this->dispatch();

        $this->assertFalse($primaryview->find('aiprovider_datacurso', null));
    }

    /**
     * A user with the capability sees the node.
     */
    public function test_node_is_added_with_the_viewreports_capability(): void {
        $this->resetAfterTest();

        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('aiprovider/datacurso:viewreports', CAP_ALLOW, $roleid, \context_system::instance()->id);

        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $primaryview = $this->dispatch();

        $this->assertNotFalse($primaryview->find('aiprovider_datacurso', null));
    }
}
