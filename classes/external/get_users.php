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

/**
 * Get users for filtering.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Get users available in the system.
 */
class get_users extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get users.
     *
     * @return array
     */
    public static function execute(): array {
        global $DB;

        // Validate context.
        $context = \context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('aiprovider/datacurso:viewreports', $context);

        try {
            // Get all active users (not deleted, not suspended).
            $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                    FROM {user} u
                    WHERE u.deleted = 0 
                      AND u.suspended = 0
                      AND u.id > 1
                    ORDER BY u.firstname ASC, u.lastname ASC";

            $users = $DB->get_records_sql($sql);

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
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'users' => [],
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Operation status'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                ])
            ),
        ]);
    }
}
