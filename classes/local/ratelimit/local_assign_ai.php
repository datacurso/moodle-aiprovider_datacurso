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

namespace aiprovider_datacurso\local\ratelimit;

use lang_string;
use MoodleQuickForm;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Extra rate-limit settings for local_assign_ai service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Industria Elearning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assign_ai {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add service-specific form elements to the tenant settings form.
     *
     * @param MoodleQuickForm $mform
     * @param string $sid Service id (e.g. 'local_assign_ai')
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sid): void {

        $prefix = "ratelimit_{$sid}";
        $enableid = "{$prefix}_allowedusers_enable";
        $usersid  = "{$prefix}_allowedusers";

        // Enable checkbox.
        $mform->addElement(
            'advcheckbox',
            $enableid,
            new lang_string('ratelimit_local_assign_ai_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_assign_ai_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($enableid, PARAM_BOOL);
        $mform->setDefault($enableid, 0);

        // Autocomplete users.
        $choices = ratelimit_settings::get_user_choices([
            'local/assign_ai:review',
            'local/assign_ai:changestatus',
            'local/assign_ai:viewdetails',
            'mod/assign:submit',
        ]);

        $attributes = ratelimit_settings::get_autocomplete_attributes();

        $mform->addElement(
            'autocomplete',
            $usersid,
            new lang_string('ratelimit_local_assign_ai_allowedusers', self::PLUGIN),
            $choices,
            $attributes
        );
        $mform->addHelpButton(
            $usersid,
            'ratelimit_local_assign_ai_allowedusers_desc',
            self::PLUGIN
        );
        $mform->setType($usersid, PARAM_RAW);

        // Conditional display.
        $mform->hideIf($usersid, $enableid, 'notchecked');
    }

    /**
     * Inject initial data for service-specific fields (tenant-aware).
     *
     * @param string    $sid
     * @param \stdClass $data
     * @param int       $tenantid
     * @return \stdClass
     */
    public function get_initial_data(
        string $sid,
        \stdClass $data,
        int $tenantid
    ): \stdClass {

        // Enable flag.
        $data->{"ratelimit_{$sid}_allowedusers_enable"} =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                "ratelimit_{$sid}_allowedusers_enable",
                get_config(self::PLUGIN, "ratelimit_{$sid}_allowedusers_enable")
            );

        // Allowed users list.
        $raw =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                "ratelimit_{$sid}_allowedusers",
                get_config(self::PLUGIN, "ratelimit_{$sid}_allowedusers")
            );

        if (!empty($raw)) {
            $data->{"ratelimit_{$sid}_allowedusers"} = explode(',', $raw);
        }

        return $data;
    }
}
