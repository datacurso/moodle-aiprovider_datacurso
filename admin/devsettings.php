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
 * Development URLs of the DataCurso AI services.
 *
 * Deliberately not linked from the admin tree, the navigation or any menu: it is reached only by
 * typing its URL, and only site administrators may use it.
 *
 * @package    aiprovider_datacurso
 * @category   admin
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use aiprovider_datacurso\form\devsettings_form;

require('../../../../config.php');

require_login(null, false);
devsettings_form::require_site_admin();

$url = new moodle_url('/ai/provider/datacurso/admin/devsettings.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('devsettings', 'aiprovider_datacurso'));
$PAGE->set_heading(get_string('devsettings', 'aiprovider_datacurso'));

$form = new devsettings_form($url);
if ($form->is_cancelled()) {
    redirect($url);
} else if ($data = $form->get_data()) {
    devsettings_form::save($data);
    redirect(
        $url,
        get_string('devsettings_saved', 'aiprovider_datacurso'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$form->set_data(devsettings_form::current_data());

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
