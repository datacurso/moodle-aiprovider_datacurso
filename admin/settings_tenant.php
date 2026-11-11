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

require_once(__DIR__ . '/../../../../config.php');

require_login();

$context = context_system::instance();
// AIP-SEC-003: viewing the reports is not enough to change the configuration.
\aiprovider_datacurso\form\settings_tenant_form::require_manage_capability();

global $PAGE, $OUTPUT;

// Page setup.
$url = new moodle_url('/ai/provider/datacurso/admin/settings_tenant.php');
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('link_provider_config', 'aiprovider_datacurso'));
$PAGE->set_heading(get_string('pluginname', 'aiprovider_datacurso'));

// Form.
$form = new \aiprovider_datacurso\form\settings_tenant_form();

// Cancel.
if ($form->is_cancelled()) {
    redirect(
        new moodle_url('/ai/provider/datacurso/admin/report_sections.php')
    );
}

// Submit. The form saves for the tenant of the current user and checks the capability again.
if ($data = $form->get_data()) {
    \aiprovider_datacurso\form\settings_tenant_form::save($data);

    redirect(
        $url,
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Initial display.
$form->set_data_for_dynamic_submission();

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
