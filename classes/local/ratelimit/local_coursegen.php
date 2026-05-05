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
 * Extra rate-limit settings for local_coursegen service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Industria Elearning
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_coursegen {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add service-specific form elements.
     *
     * @param MoodleQuickForm $mform
     * @param string $sid Service id (local_coursegen)
     */
    public function add_form_elements(MoodleQuickForm $mform, string $sid): void {

        $prefix = "ratelimit_{$sid}";
        $enableid = "{$prefix}_allowedusers_enable";

        // Enable checkbox.
        $mform->addElement(
            'advcheckbox',
            $enableid,
            new lang_string('ratelimit_local_coursegen_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_coursegen_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($enableid, PARAM_BOOL);
        $mform->setDefault($enableid, 0);

        $coursecreatorsid = "{$prefix}_coursecreators";

        $coursecreatorchoices = ratelimit_settings::get_user_choices([
            'moodle/course:create',
            'local/coursegen:createcoursewithai',
        ]);

        $mform->addElement(
            'autocomplete',
            $coursecreatorsid,
            new lang_string('ratelimit_local_coursegen_coursecreators', self::PLUGIN),
            $coursecreatorchoices,
            ratelimit_settings::get_autocomplete_attributes()
        );
        $mform->addHelpButton(
            $coursecreatorsid,
            'ratelimit_local_coursegen_coursecreators_desc',
            self::PLUGIN
        );
        $mform->setType($coursecreatorsid, PARAM_RAW);
        $mform->hideIf($coursecreatorsid, $enableid, 'notchecked');

        $activitycreatorsid = "{$prefix}_activitycreators";

        $activitycreatorchoices = ratelimit_settings::get_user_choices([
            'moodle/course:manageactivities',
            'local/coursegen:createactivitywithai',
        ]);

        $mform->addElement(
            'autocomplete',
            $activitycreatorsid,
            new lang_string('ratelimit_local_coursegen_activitycreators', self::PLUGIN),
            $activitycreatorchoices,
            ratelimit_settings::get_autocomplete_attributes()
        );
        $mform->addHelpButton(
            $activitycreatorsid,
            'ratelimit_local_coursegen_activitycreators_desc',
            self::PLUGIN
        );
        $mform->setType($activitycreatorsid, PARAM_RAW);
        $mform->hideIf($activitycreatorsid, $enableid, 'notchecked');
    }

    /**
     * Inject initial values for service-specific fields (tenant-aware).
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

        // Multi-user fields.
        foreach (['coursecreators', 'activitycreators'] as $field) {
            $raw =
                \aiprovider_datacurso\local\tenant_config::get(
                    self::PLUGIN,
                    $tenantid,
                    "ratelimit_{$sid}_{$field}",
                    get_config(self::PLUGIN, "ratelimit_{$sid}_{$field}")
                );

            if (!empty($raw)) {
                $data->{"ratelimit_{$sid}_{$field}"} = explode(',', $raw);
            }
        }

        return $data;
    }
}
