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
 * Moodleform adapter for Life Story report AI rate limit settings.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lifestory {
    /** Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add form elements to tenant settings form.
     *
     * @param \MoodleQuickForm $mform
     * @param string $sid Service id (e.g. 'report_lifestory')
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $sid): void {

        $prefix = "ratelimit_{$sid}";
        $enableid = "{$prefix}_allowedusers_enable";

        // Enable checkbox.
        $mform->addElement(
            'advcheckbox',
            $enableid,
            get_string('ratelimit_report_lifestory_allowedusers_enable', self::PLUGIN),
            get_string('ratelimit_report_lifestory_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($enableid, PARAM_BOOL);
        $mform->setDefault($enableid, 0);

        $attributes = ratelimit_settings::get_autocomplete_attributes();

        // Allowed users.
        $choices = ratelimit_settings::get_user_choices([
            'report/lifestory:generateaifeedback',
        ]);

        $allowedusersid = "{$prefix}_allowedusers";
        $mform->addElement(
            'autocomplete',
            $allowedusersid,
            get_string('ratelimit_report_lifestory_allowedusers', self::PLUGIN),
            $choices,
            $attributes
        );
        $mform->setType($allowedusersid, PARAM_RAW);

        // Hide if disabled.
        $mform->hideIf($allowedusersid, $enableid, 'eq', 0);
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

        // Allowed users.
        $userskey = "ratelimit_{$sid}_allowedusers";
        $raw =
            \aiprovider_datacurso\local\tenant_config::get(
                self::PLUGIN,
                $tenantid,
                $userskey,
                get_config(self::PLUGIN, $userskey)
            );

        if (!empty($raw)) {
            $data->{$userskey} = explode(',', $raw);
        }

        return $data;
    }
}
