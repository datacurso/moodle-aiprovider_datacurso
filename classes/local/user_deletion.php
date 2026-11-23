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
 * Keeps the local consumption history and the pseudonym cache in step with the deletion of a user.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class user_deletion {
    /**
     * Unlink a deleted user from the consumption history: the records already copied to the local
     * history are attributed to nobody (userid 0), and the pseudonym of the user is dropped from the
     * reverse map so the records synchronised later are not attributed to the account either.
     *
     * The records are kept, unlinked, so the credits stay in the totals of the site.
     *
     * Observer of {@see \core\event\user_deleted}, declared in db/events.php.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;

        $DB->set_field('aiprovider_datacurso_consumption', 'userid', 0, ['userid' => $event->objectid]);
        outbound_privacy::forget_user((int) $event->objectid);
    }
}
