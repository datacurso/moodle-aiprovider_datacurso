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
 * Admin report sections for Datacurso AI Provider plugin.
 *
 * This file manages the main administrative report sections of the
 * Datacurso AI Provider plugin. It renders tabs for:
 * - AI consumption history
 * - General usage report
 * - Installed plugins list
 *
 * @package    aiprovider_datacurso
 * @category   admin
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');

// Ensure the user is logged in and has permission to access this page.
$context = context_system::instance();
require_login();
require_capability('aiprovider/datacurso:viewreports', $context);

// Set up page context and layout.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/ai/provider/datacurso/admin/report_sections.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('reportspagetitle', 'aiprovider_datacurso'));

// Get the current tab parameter. Consumption history is the default landing tab.
$tab = optional_param('tab', 'consumption', PARAM_ALPHAEXT);

// Define tabs for navigation.
$tabs = [];
$tabs[] = new tabobject(
    'consumption',
    new moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'consumption']),
    get_string('link_consumptionhistory', 'aiprovider_datacurso')
);

$tabs[] = new tabobject(
    'generalreport',
    new moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'generalreport']),
    get_string('link_generalreport', 'aiprovider_datacurso')
);

$tabs[] = new tabobject(
    'pluginslist',
    new moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'pluginslist']),
    get_string('link_listplugings', 'aiprovider_datacurso')
);

global $DB;

$manager = new \core_ai\manager($DB);
$instances = $manager->get_provider_instances();
$idprovider = null;
foreach ($instances as $instance) {
    if ($instance->get_name() === 'aiprovider_datacurso') {
        $idprovider = $instance->id;
        break;
    }
}

if ($idprovider) {
    $tabs[] = new tabobject(
        'configprovider',
        new moodle_url('/ai/configure.php', ['id' => $idprovider]),
        get_string('link_provider_config', 'aiprovider_datacurso')
    );
}

// Render page header and navigation.
echo $OUTPUT->header();

// Render custom header logo output.
$headerlogo = new \aiprovider_datacurso\output\header_logo();
echo $OUTPUT->render($headerlogo);

// Render tab navigation.
echo $OUTPUT->tabtree($tabs, $tab);

// Load tab content.
switch ($tab) {
    case 'consumption':
        // Sync the local mirror from the external API, then render the Report Builder system report.
        \aiprovider_datacurso\local\sync\consumption_sync::sync();
        $report = \core_reportbuilder\system_report_factory::create(
            \aiprovider_datacurso\reportbuilder\local\systemreports\consumption_history::class,
            $context
        );
        echo $report->output();
        break;

    case 'generalreport':
        // Keep the local mirror fresh so the charts read up-to-date data from it (not the shop).
        \aiprovider_datacurso\local\sync\consumption_sync::sync();

        // Render general statistics and charts.
        $page = new \aiprovider_datacurso\output\report_page();
        echo $OUTPUT->render($page);
        $PAGE->requires->js_call_amd('aiprovider_datacurso/report_charts', 'init');
        break;

    case 'pluginslist':
        // Datacurso suite plugins with their install status, their state in the provider and a link to
        // their settings page here.
        $page = new \aiprovider_datacurso\output\plugin_list_page(\aiprovider_datacurso\local\suite_plugins::get_overview());
        echo $OUTPUT->render($page);
        break;
}

// Render page footer.
echo $OUTPUT->footer();
