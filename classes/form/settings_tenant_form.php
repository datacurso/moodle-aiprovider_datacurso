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

namespace aiprovider_datacurso\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/**
 * Tenant-aware configuration form for Datacurso AI Provider.
 *
 * This form replaces settings.php UI and will later persist data
 * to mdl_aiprovider_datacurso_tenant_config instead of mdl_config_plugins.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_tenant_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('settings', 'core'));

        $mform->addElement(
            'passwordunmask',
            'licensekey',
            get_string('licensekey', 'aiprovider_datacurso')
        );
        $mform->addHelpButton('licensekey', 'licensekey', 'aiprovider_datacurso');

        $mform->addElement(
            'header',
            'ratelimits',
            get_string('ratelimits_heading', 'aiprovider_datacurso')
        );

        $services = \aiprovider_datacurso\provider::get_services();
        \core_collator::asort_array_of_arrays_by_key($services, 'name');

        foreach ($services as $service) {
            $sid   = $service['id'];
            $sname = $service['name'];

            // Service section header.
            $mform->addElement(
                'header',
                "ratelimit_{$sid}_header",
                format_string($sname)
            );

            // Enable.
            $mform->addElement(
                'advcheckbox',
                "ratelimit_{$sid}_enable",
                get_string('ratelimit_enable', 'aiprovider_datacurso'),
                get_string('ratelimit_enable_desc', 'aiprovider_datacurso')
            );
            $mform->setType("ratelimit_{$sid}_enable", PARAM_BOOL);

            // Limit.
            $mform->addElement(
                'text',
                "ratelimit_{$sid}_limit",
                get_string('ratelimit_limit', 'aiprovider_datacurso'),
                ['size' => 10]
            );
            $mform->setType("ratelimit_{$sid}_limit", PARAM_INT);
            $mform->addHelpButton(
                "ratelimit_{$sid}_limit",
                'ratelimit_limit',
                'aiprovider_datacurso'
            );
            $mform->hideIf(
                "ratelimit_{$sid}_limit",
                "ratelimit_{$sid}_enable",
                'eq',
                0
            );

            // Window value + unit.
            $mform->addElement(
                'text',
                "ratelimit_{$sid}_window_value",
                get_string('ratelimit_window_value', 'aiprovider_datacurso'),
                ['size' => 5]
            );
            $mform->setType("ratelimit_{$sid}_window_value", PARAM_INT);

            $units = [
                'seconds' => get_string('seconds'),
                'minutes' => get_string('minutes'),
                'hours'   => get_string('hours'),
                'days'    => get_string('days'),
                'months'  => get_string('months'),
                'years'   => get_string('years'),
            ];

            $mform->addElement(
                'select',
                "ratelimit_{$sid}_window_unit",
                get_string('ratelimit_window_unit', 'aiprovider_datacurso'),
                $units
            );
            $mform->setType(
                "ratelimit_{$sid}_window_unit",
                PARAM_ALPHANUMEXT
            );

            $mform->hideIf(
                "ratelimit_{$sid}_window_value",
                "ratelimit_{$sid}_enable",
                'eq',
                0
            );
            $mform->hideIf(
                "ratelimit_{$sid}_window_unit",
                "ratelimit_{$sid}_enable",
                'eq',
                0
            );

            $classname = "\\aiprovider_datacurso\\local\\ratelimit\\{$sid}";
            if (class_exists($classname)) {
                $serviceconfig = new $classname();

                if (method_exists($serviceconfig, 'add_form_elements')) {
                    $serviceconfig->add_form_elements($mform, $sid);
                }
            }
        }

        $this->add_action_buttons(true);
    }

    /**
     * Initial data population (tenant-aware).
     */
    protected function get_initial_data(): \stdClass {
        global $USER;

        $data = new \stdClass();

        $tenantid = \tool_tenant\tenancy::get_tenant_id($USER->id);

        $data->licensekey =
            \aiprovider_datacurso\local\tenant_config::get(
                'aiprovider_datacurso',
                $tenantid,
                'licensekey',
                get_config('aiprovider_datacurso', 'licensekey')
            );

        $services = \aiprovider_datacurso\provider::get_services();

        foreach ($services as $service) {
            $sid = $service['id'];

            $data->{"ratelimit_{$sid}_enable"} =
                \aiprovider_datacurso\local\tenant_config::get(
                    'aiprovider_datacurso',
                    $tenantid,
                    "ratelimit_{$sid}_enable",
                    get_config('aiprovider_datacurso', "ratelimit_{$sid}_enable")
                );

            $data->{"ratelimit_{$sid}_limit"} =
                \aiprovider_datacurso\local\tenant_config::get(
                    'aiprovider_datacurso',
                    $tenantid,
                    "ratelimit_{$sid}_limit",
                    get_config('aiprovider_datacurso', "ratelimit_{$sid}_limit")
                );
            // Window value + unit.
            $windowvalue =
                \aiprovider_datacurso\local\tenant_config::get(
                    'aiprovider_datacurso',
                    $tenantid,
                    "ratelimit_{$sid}_window_value"
                );

            $windowunit =
                \aiprovider_datacurso\local\tenant_config::get(
                    'aiprovider_datacurso',
                    $tenantid,
                    "ratelimit_{$sid}_window_unit"
                );

            // Fallback legacy JSON window.
            if ($windowvalue === null || $windowunit === null) {
                $window = get_config('aiprovider_datacurso', "ratelimit_{$sid}_window");
                if ($window) {
                    $decoded = json_decode($window);
                    if ($windowvalue === null && !empty($decoded->value)) {
                        $windowvalue = (int)$decoded->value;
                    }
                    if ($windowunit === null && !empty($decoded->unit)) {
                        $windowunit = $decoded->unit;
                    }
                }
            }

            if ($windowvalue !== null) {
                $data->{"ratelimit_{$sid}_window_value"} = (int)$windowvalue;
            }
            if ($windowunit !== null) {
                $data->{"ratelimit_{$sid}_window_unit"} = $windowunit;
            }

            $classname = "\\aiprovider_datacurso\\local\\ratelimit\\{$sid}";
            if (class_exists($classname)) {
                $serviceconfig = new $classname();
                if (method_exists($serviceconfig, 'get_initial_data')) {
                    $data = $serviceconfig->get_initial_data($sid, $data, $tenantid);
                }
            }
        }

        return $data;
    }

    /**
     * Required for dynamic submissions.
     */
    public function set_data_for_dynamic_submission(): void {
         $this->set_data($this->get_initial_data());
    }
}
