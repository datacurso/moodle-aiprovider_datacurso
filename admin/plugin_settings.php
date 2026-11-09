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
 * On Moodle Workplace the "Enabled" field is kept per tenant: a tenant administrator edits the state of
 * their own tenant, and a site administrator edits the site default (tenantid=0) or any tenant. The fields
 * of the plugin's extension are kept per site, so they are only offered on the site default.
 *
 * @package    aiprovider_datacurso
 * @category   admin
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use aiprovider_datacurso\form\plugin_settings_form;
use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\suite_plugins;
use aiprovider_datacurso\local\tenant_resolver;

require('../../../../config.php');

$component = required_param('component', PARAM_COMPONENT);
$tenantparam = optional_param('tenantid', null, PARAM_INT);

$context = context_system::instance();
require_login();
$tenantid = plugin_settings::require_manage_scope($tenantparam);
plugin_settings_form::require_suite_plugin($component);

$pluginname = get_string(suite_plugins::get($component)['namestring'], 'aiprovider_datacurso');
$listparams = ['tab' => 'pluginslist'];
$urlparams = ['component' => $component];
if (tenant_resolver::is_tenancy_available() && is_siteadmin()) {
    $listparams['plugintenant'] = $tenantid;
    $urlparams['tenantid'] = $tenantid;
}
$returnurl = new moodle_url('/ai/provider/datacurso/admin/report_sections.php', $listparams);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/ai/provider/datacurso/admin/plugin_settings.php', $urlparams));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname));
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add(
    get_string('pluginname', 'aiprovider_datacurso'),
    new moodle_url('/ai/provider/datacurso/admin/report_sections.php')
);
$PAGE->navbar->add(get_string('link_listplugings', 'aiprovider_datacurso'), $returnurl);
$PAGE->navbar->add(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname), $PAGE->url);

$form = new plugin_settings_form($PAGE->url, ['component' => $component, 'tenantid' => $tenantid]);
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    plugin_settings_form::save($component, $data, $tenantid);
    redirect(
        $returnurl,
        get_string('pluginsettings_saved', 'aiprovider_datacurso', $pluginname),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}
$form->set_data(plugin_settings_form::current_data($component, $tenantid));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginsettings_title', 'aiprovider_datacurso', $pluginname));
if (tenant_resolver::is_tenancy_available()) {
    $notice = $tenantid === tenant_resolver::NO_TENANT ? 'pluginscope_site_desc' : 'pluginscope_tenant_desc';
    echo $OUTPUT->notification(get_string($notice, 'aiprovider_datacurso'), \core\output\notification::NOTIFY_INFO);
}
$form->display();
echo $OUTPUT->footer();
