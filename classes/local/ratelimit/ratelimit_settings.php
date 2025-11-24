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
 * Utility class providing common functionality for rate limit form elements.
 *
 * The abstract method add_settings is removed as per-service settings are now handled
 * via the hook listener calling the add_form_elements method directly on service classes.
 *
 * @package     aiprovider_datacurso
 * @category    admin
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\local\ratelimit;

/**
 * Utility class to manage common rate limit configurations.
 */
class ratelimit_settings {
    /**
     * Retrieve the list of selectable users for the autocomplete control.
     *
     * @param array $capabilities Capability names users must have to be selectable.
     * @return array<string,string>
     */
    public static function get_user_choices(array $capabilities): array {
        global $DB, $CFG;

        [$insql, $params] = $DB->get_in_or_equal($capabilities, SQL_PARAMS_NAMED);

        $namefields = 'u.id, u.firstname, u.lastname, u.alternatename, u.middlename, u.firstnamephonetic, u.lastnamephonetic'; // <--- CORRECCIÓN CLAVE

        $params['deleted'] = 0;
        $params['suspended'] = 0;
        $params['permission'] = CAP_ALLOW;
        $params['capabilitiescount'] = count($capabilities);

        $records = $DB->get_records_sql(
            "SELECT {$namefields}
            FROM
                {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE
                rc.permission = :permission
                AND u.deleted = :deleted
                AND u.suspended = :suspended
                AND rc.capability {$insql}
            GROUP BY
                u.id, u.firstname, u.lastname, u.alternatename, u.middlename, u.firstnamephonetic, u.lastnamephonetic
            HAVING
                COUNT(DISTINCT rc.capability) = :capabilitiescount
            ORDER BY u.lastname, u.firstname, u.id",
            $params
        );

        $choices = [];
        foreach ($records as $user) {
            $choices[(string)$user->id] = fullname($user);
        }

        if (empty($choices)) {
            return ['' => get_string('noselection', 'form')];
        }

        return $choices;
    }

    /**
     * Get the autocomplete attributes for the user selection.
     *
     * @return array
     */
    public static function get_autocomplete_attributes(): array {
        return [
            'ajax' => 'core_user\output\user_autocomplete',
            'multiple' => true,
            'showsuggestions' => true,
            'placeholder' => get_string('search'),
            'noselectionstring' => get_string('noselection', 'form'),
        ];
    }
}
