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
 * Tests for the unlinking of a deleted user from the local consumption history.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(user_deletion::class)]
final class user_deletion_test extends \advanced_testcase {
    /**
     * Deleting a user unlinks the consumption records already copied to the local history.
     */
    public function test_deleted_user_consumption_rows_are_unlinked(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        foreach ([[1, $user->id], [2, $user->id], [3, $other->id]] as [$externalid, $userid]) {
            $DB->insert_record('aiprovider_datacurso_consumption', (object) [
                'externalid' => $externalid,
                'userid' => $userid,
                'service' => 'local_coursegen',
                'action' => '/course/execute',
                'credits' => 5,
                'balance' => 100,
                'timecreated' => time(),
            ]);
        }

        delete_user($user);

        $this->assertSame(0, $DB->count_records('aiprovider_datacurso_consumption', ['userid' => $user->id]));
        // The credits stay in the site totals, attributed to nobody.
        $this->assertSame(2, $DB->count_records('aiprovider_datacurso_consumption', ['userid' => 0]));
        $this->assertSame(1, $DB->count_records('aiprovider_datacurso_consumption', ['userid' => $other->id]));
    }
}
