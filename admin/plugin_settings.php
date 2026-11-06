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
 * Settings of one Datacurso suite plugin: "Enabled" plus the fields of the plugin's extension.
 *
 * @package    aiprovider_datacurso
 * @category   admin
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use aiprovider_datacurso\form\config_form;
use aiprovider_datacurso\form\plugin_settings_form;
use aiprovider_datacurso\local\suite_plugins;

require('../../../../config.php');

$component = required_param('component', PARAM_COMPONENT);

$context = context_system::instance();
require_login();
config_form::require_manage_capability();
plugin_settings_form::require_suite_plugin($component);

$pluginname = get_string(suite_plugins::get($component)['namestring'], 'aiprovider_datacurso');
$returnurl = new moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'pluginslist']);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/ai/provider/datacurso/admin/plugin_settings.php', ['component' => $component]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname));
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add(
    get_string('pluginname', 'aiprovider_datacurso'),
    new moodle_url('/ai/provider/datacurso/admin/report_sections.php')
);
$PAGE->navbar->add(get_string('link_listplugings', 'aiprovider_datacurso'), $returnurl);
$PAGE->navbar->add(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname), $PAGE->url);

$form = new plugin_settings_form($PAGE->url, ['component' => $component]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    plugin_settings_form::save($component, $data);
    redirect(
        $returnurl,
        get_string('pluginsettings_saved', 'aiprovider_datacurso', $pluginname),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$form->set_data(plugin_settings_form::current_data($component));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname));
$form->display();
echo $OUTPUT->footer();
