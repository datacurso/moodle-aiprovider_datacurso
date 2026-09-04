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

/**
 * Plugin administration pages are defined here.
 *
 * @package     aiprovider_datacurso
 * @category    admin
 * @copyright   Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_ai\admin\admin_settingspage_provider;

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingspage_provider(
        'aiprovider_datacurso',
        new lang_string('pluginname', 'aiprovider_datacurso'),
        'moodle/site:config',
        true
    );

    // Keep plugin settings page visible and redirect provider config to tenant-aware UI.
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'aiprovider_datacurso/configlink',
            get_string('settings', 'core'),
            html_writer::link(
                new moodle_url('/ai/provider/datacurso/admin/settings_tenant.php'),
                get_string('link_provider_config_tenant', 'aiprovider_datacurso')
            )
        ));

        // License key.
        $settings->add(new admin_setting_configpasswordunmask(
            'aiprovider_datacurso/licensekey',
            new lang_string('licensekey', 'aiprovider_datacurso'),
            new lang_string('licensekey_desc', 'aiprovider_datacurso'),
            ''
        ));

        // Per-plugin rate limit settings now live in the dedicated Configuration page
        // (Datacurso AI Provider → Configuration), which is the single source of truth.
        $settings->add(new admin_setting_description(
            'aiprovider_datacurso/ratelimits_moved',
            new lang_string('ratelimits_heading', 'aiprovider_datacurso'),
            new lang_string('ratelimits_moved_desc', 'aiprovider_datacurso', (object) [
                'url' => (new moodle_url('/ai/provider/datacurso/admin/report_sections.php', ['tab' => 'config']))->out(),
            ])
        ));
    }

    $ADMIN->add('reports', new admin_externalpage(
        'aiprovider_datacurso_reports',
        get_string('link_generalreport_datacurso', 'aiprovider_datacurso'),
        new moodle_url('/ai/provider/datacurso/admin/report_sections.php'),
        'moodle/site:config'
    ));
}
