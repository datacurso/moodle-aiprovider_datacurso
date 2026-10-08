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

namespace aiprovider_datacurso\local\service;

/**
 * Service class for user-related operations.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_service {
    /**
     * Get users available in the system with optional search.
     *
     * @param string $search Search query.
     * @param \moodle_database|null $db Database to query; defaults to the global $DB.
     * @return array
     */
    public static function get_users(string $search = '', ?\moodle_database $db = null): array {
        global $DB;

        $db = $db ?? $DB;

        try {
            $where = "u.deleted = 0 AND u.suspended = 0 AND u.id > 1";
            $sqlparams = [];

            if (!empty($search)) {
                $searchsql = $db->sql_like('u.firstname', ':search1', false, false) . ' OR ' .
                             $db->sql_like('u.lastname', ':search2', false, false) . ' OR ' .
                             $db->sql_like($db->sql_fullname('u.firstname', 'u.lastname'), ':search3', false, false);
                $where .= " AND ($searchsql)";
                $sqlparams['search1'] = '%' . $search . '%';
                $sqlparams['search2'] = '%' . $search . '%';
                $sqlparams['search3'] = '%' . $search . '%';
            }

            // Select every name field so fullname() can build the display name without notices.
            $namefields = \core_user\fields::for_name()->get_sql('u')->selects;

            $sql = "SELECT DISTINCT u.id{$namefields}
                    FROM {user} u
                    WHERE $where
                    ORDER BY u.firstname ASC, u.lastname ASC";

            $users = $db->get_records_sql($sql, $sqlparams, 0, 20);

            $result = [];
            foreach ($users as $user) {
                $result[] = [
                    'id' => $user->id,
                    'fullname' => fullname($user),
                ];
            }

            return [
                'status' => 'success',
                'users' => $result,
            ];
        } catch (\Exception $e) {
            // Database errors may expose the table prefix or driver details: keep them for developers only.
            debugging('Could not retrieve users: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'status' => 'error',
                'message' => get_string('errorgetusers', 'aiprovider_datacurso'),
                'users' => [],
            ];
        }
    }
}
