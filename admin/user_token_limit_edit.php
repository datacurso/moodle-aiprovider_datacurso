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

require('../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use aiprovider_datacurso\form\user_token_limit_form;
use aiprovider_datacurso\local\user_token_limit_manager;

$id = optional_param('id', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$listurl = new moodle_url('/ai/provider/datacurso/admin/user_token_limits.php');

$urlparams = ['id' => $id];
if (!empty($returnurl)) {
    $urlparams['returnurl'] = $returnurl;
}
$pageurl = new moodle_url('/ai/provider/datacurso/admin/user_token_limit_edit.php', $urlparams);
admin_externalpage_setup('aiprovider_datacurso_userlimits', '', null, $pageurl);

$context = context_system::instance();
require_capability('aiprovider/datacurso:managetokenlimits', $context);

$data = new stdClass();
$heading = get_string('usertokenlimit_add_title', 'aiprovider_datacurso');

if ($id) {
    $record = user_token_limit_manager::get_by_id($id);
    if (!$record) {
        throw new moodle_exception('error_usertokenlimit_notfound', 'aiprovider_datacurso');
    }

    $user = \core_user::get_user($record->userid, '*', MUST_EXIST);
    $data->id = $record->id;
    $data->userid = $record->userid;
    $data->tokenlimit = $record->tokenlimit;
    $data->resetusage = 0;
    $data->userlabel = fullname($user) . ' (' . $user->email . ')';
    $heading = get_string('usertokenlimit_edit_title', 'aiprovider_datacurso', fullname($user));
} else {
    $data->tokenlimit = 0;
}

$form = new user_token_limit_form(null, ['data' => $data, 'returnurl' => $returnurl]);

if ($form->is_cancelled()) {
    redirect($returnurl ?: $listurl);
}

if ($formdata = $form->get_data()) {
    $resetusage = !empty($formdata->resetusage);
    user_token_limit_manager::save((int)$formdata->userid, (int)$formdata->tokenlimit, $formdata->id ?: null, $resetusage);
    $redirecturl = !empty($formdata->returnurl) ? new moodle_url($formdata->returnurl) : $listurl;
    redirect(
        $redirecturl,
        get_string('usertokenlimit_saved', 'aiprovider_datacurso'),
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_title($heading);
$PAGE->set_heading($heading);

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
$form->set_data($data);
$form->display();
echo $OUTPUT->footer();
