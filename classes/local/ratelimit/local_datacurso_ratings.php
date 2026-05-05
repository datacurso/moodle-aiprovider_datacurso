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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Moodleform adapter for Datacurso ratings AI rate limit settings.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_datacurso_ratings {
    /** Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add form elements to tenant settings form.
     *
     * @param \MoodleQuickForm $mform
     * @param string $sid Service id (e.g. 'local_datacurso_ratings')
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $sid): void {

        $prefix = "ratelimit_{$sid}";
        $enableid = "{$prefix}_allowedusers_enable";

        // Enable checkbox.
        $mform->addElement(
            'advcheckbox',
            $enableid,
            get_string('ratelimit_local_datacurso_ratings_allowedusers_enable', self::PLUGIN),
            get_string('ratelimit_local_datacurso_ratings_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($enableid, PARAM_BOOL);
        $mform->setDefault($enableid, 0);

        $attributes = ratelimit_settings::get_autocomplete_attributes();

        // Course / activity analysts.
        $coursechoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysiscourse',
            'local/datacurso_ratings:generateanalysisactivity',
        ]);

        $courseid = "{$prefix}_courseanalysts";
        $mform->addElement(
            'autocomplete',
            $courseid,
            get_string('ratelimit_local_datacurso_ratings_courseanalysts', self::PLUGIN),
            $coursechoices,
            $attributes
        );
        $mform->setType($courseid, PARAM_RAW);

        // Hide if disabled.
        $mform->hideIf($courseid, $enableid, 'eq', 0);

        // General analysts.
        $generalchoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysisgeneral',
        ]);

        $generalid = "{$prefix}_generalanalysts";
        $mform->addElement(
            'autocomplete',
            $generalid,
            get_string('ratelimit_local_datacurso_ratings_generalanalysts', self::PLUGIN),
            $generalchoices,
            $attributes
        );
        $mform->setType($generalid, PARAM_RAW);

        // Hide if disabled.
        $mform->hideIf($generalid, $enableid, 'eq', 0);
    }

    /**
     * Populate initial form data from tenant config (with fallback).
     *
     * @param string    $sid Service id.
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
        $enablekey = "ratelimit_{$sid}_allowedusers_enable";
        $data->{$enablekey} =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                $enablekey,
                get_config(self::PLUGIN, $enablekey)
            );

        // Course analysts.
        $coursekey = "ratelimit_{$sid}_courseanalysts";
        $rawcourse =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                $coursekey,
                get_config(self::PLUGIN, $coursekey)
            );
        if (!empty($rawcourse)) {
            $data->{$coursekey} = explode(',', $rawcourse);
        }

        // General analysts.
        $generalkey = "ratelimit_{$sid}_generalanalysts";
        $rawgeneral =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                $generalkey,
                get_config(self::PLUGIN, $generalkey)
            );
        if (!empty($rawgeneral)) {
            $data->{$generalkey} = explode(',', $rawgeneral);
        }
        return $data;
    }
}
