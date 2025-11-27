<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit per-user token limits.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_token_limit_form extends \moodleform {
    /**
     * Define the form controls.
     */
    public function definition(): void {
        $mform = $this->_form;
        $data = $this->_customdata['data'] ?? null;

        $editing = !empty($data) && !empty($data->id);

        if ($editing) {
            $mform->addElement('static', 'userlabel', get_string('usertokenlimit_user', 'aiprovider_datacurso'), format_string($data->userlabel));
            $mform->addElement('hidden', 'userid');
            $mform->setType('userid', PARAM_INT);
            $mform->setDefault('userid', $data->userid);
        } else {
            $options = [
                'ajax' => 'core_user/form_user_selector',
                'multiple' => false,
                'placeholder' => get_string('search'),
                'noselectionstring' => get_string('noselection', 'form'),
            ];
            $mform->addElement('autocomplete', 'userid', get_string('usertokenlimit_user', 'aiprovider_datacurso'), [], $options);
            $mform->addRule('userid', get_string('required'), 'required', null, 'client');
            $mform->setType('userid', PARAM_INT);
        }

        $mform->addElement('text', 'tokenlimit', get_string('usertokenlimit_limit', 'aiprovider_datacurso'));
        $mform->setType('tokenlimit', PARAM_INT);
        $mform->addRule('tokenlimit', get_string('required'), 'required', null, 'client');
        $mform->addRule('tokenlimit', null, 'numeric', null, 'client');

        if ($editing) {
            $mform->addElement('advcheckbox', 'resetusage', get_string('usertokenlimit_reset', 'aiprovider_datacurso'));
        }

        $mform->addElement('hidden', 'id', $data->id ?? 0);
        $mform->setType('id', PARAM_INT);

        $defaultreturn = $this->_customdata['returnurl'] ?? '';
        $mform->addElement('hidden', 'returnurl', $defaultreturn);
        $mform->setType('returnurl', PARAM_LOCALURL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $tokenlimit = (int)($data['tokenlimit'] ?? 0);
        if ($tokenlimit < 0) {
            $errors['tokenlimit'] = get_string('usertokenlimit_limit_invalid', 'aiprovider_datacurso');
        }

        if (empty($data['id']) && empty($data['userid'])) {
            $errors['userid'] = get_string('required');
        }

        return $errors;
    }
}
