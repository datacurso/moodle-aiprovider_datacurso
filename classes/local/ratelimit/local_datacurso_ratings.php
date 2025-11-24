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

use core_user\form\element\autocomplete; 
use \lang_string;
use aiprovider_datacurso\local\ratelimit\ratelimit_settings;
use core_form\quickform;

defined('MOODLE_INTERNAL') || die();

/**
 * Class local_datacurso_ratings
 *
 * This class provides the specific rate limiting settings elements for the 
 * Ratings Analysis AI service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_datacurso_ratings {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Adds the rate limit form elements specific to ratings analysis with AI.
     *
     * @param \moodleform $mform The Moodle form object (ai_provider_form). 
     * @param string $serviceid The service identifier, e.g., 'local_datacurso_ratings'.
     */
    public function add_form_elements($mform, string $serviceid): void {

        $configprefix = "ratelimit_{$serviceid}";
        $allowedusersenable_id = "{$configprefix}_allowedusers_enable";

        // 1. Checkbox to enable limiting by allowed users list.
        $mform->addElement(
            'checkbox',
            $allowedusersenable_id,
            new lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($allowedusersenable_id, PARAM_BOOL);
        $mform->setDefault($allowedusersenable_id, 0);

        // Define attributes for the Autocomplete element.
        $attributes = ratelimit_settings::get_autocomplete_attributes();
        $attributes['multiple'] = true; // Ensure multiple selection is allowed.

        // 2. Course analysis generators (Autocomplete field).
        $coursechoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysiscourse',
            'local/datacurso_ratings:generateanalysisactivity',
        ]);
        $courseanalysts_id = "{$configprefix}_courseanalysts";

        $mform->addElement(
            'autocomplete',
            $courseanalysts_id,
            new lang_string('ratelimit_local_datacurso_ratings_courseanalysts', self::PLUGIN),
            $coursechoices,
            $attributes
        );
        $mform->setType($courseanalysts_id, PARAM_RAW);

        // Hide if the master checkbox is not checked.
        $mform->hideIf($courseanalysts_id, $allowedusersenable_id, 'notchecked');

        // 3. General analysis generators (Autocomplete field).
        $generalchoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysisgeneral',
        ]);
        $generalanalysts_id = "{$configprefix}_generalanalysts";

        $mform->addElement(
            'autocomplete',
            $generalanalysts_id,
            new lang_string('ratelimit_local_datacurso_ratings_generalanalysts', self::PLUGIN),
            $generalchoices,
            $attributes
        );
        $mform->setType($generalanalysts_id, PARAM_RAW);

        // Hide if the master checkbox is not checked.
        $mform->hideIf($generalanalysts_id, $allowedusersenable_id, 'notchecked');
    }
}
