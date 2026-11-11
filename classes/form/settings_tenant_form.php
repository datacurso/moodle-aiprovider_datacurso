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

use aiprovider_datacurso\local\license_region;
use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\provider_status;
use aiprovider_datacurso\local\tenant_config;
use aiprovider_datacurso\local\tenant_resolver;

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

        // Switches the provider off for this tenant only; the site switch in AI providers still wins.
        $mform->addElement(
            'advcheckbox',
            provider_status::CONFIG_NAME,
            get_string('provider_enabled', 'aiprovider_datacurso'),
            get_string('provider_enabled_desc', 'aiprovider_datacurso')
        );
        $mform->setType(provider_status::CONFIG_NAME, PARAM_BOOL);
        $mform->setDefault(provider_status::CONFIG_NAME, 1);

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

            // Credits per action: bold title in the label column, description alongside.
            $mform->addElement(
                'static',
                "cpahead_{$sid}",
                \html_writer::tag(
                    'strong',
                    get_string('ratelimit_creditperaction', 'aiprovider_datacurso'),
                    ['class' => 'h5']
                ),
                \html_writer::tag(
                    'span',
                    get_string('ratelimit_creditperaction_desc', 'aiprovider_datacurso'),
                    ['class' => 'text-muted']
                )
            );
            $mform->hideIf("cpahead_{$sid}", "ratelimit_{$sid}_enable", 'eq', 0);

            foreach (\aiprovider_datacurso\provider::get_actions_for_service($sid) as $action) {
                $key = $action['key'];
                $mform->addElement('text', "credit[{$sid}][{$key}]", (string) $action['name'], ['size' => 8]);
                $mform->setType("credit[{$sid}][{$key}]", PARAM_INT);
                $mform->addHelpButton("credit[{$sid}][{$key}]", 'ratelimit_creditperaction', 'aiprovider_datacurso');
                $mform->hideIf("credit[{$sid}][{$key}]", "ratelimit_{$sid}_enable", 'eq', 0);
            }

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
     * Reject negative per-action credit costs.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        foreach (($data['credit'] ?? []) as $sid => $map) {
            foreach ((array) $map as $key => $value) {
                if ((int) $value < 0) {
                    $errors["credit[{$sid}][{$key}]"] = get_string('error_positivevalue', 'aiprovider_datacurso');
                }
            }
        }

        return $errors;
    }

    /**
     * Initial data population (tenant-aware).
     */
    protected function get_initial_data(): \stdClass {
        global $USER;

        $data = new \stdClass();

        $tenantid = \aiprovider_datacurso\local\tenant_resolver::get_tenant_id((int) $USER->id);

        $data->{provider_status::CONFIG_NAME} = provider_status::is_tenant_disabled($tenantid) ? 0 : 1;

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

            // Credits per action: stored map (tenant override or site-wide fallback),
            // merged with the catalog default for any key not yet configured.
            $storedcredit = \aiprovider_datacurso\local\tenant_config::get(
                'aiprovider_datacurso',
                $tenantid,
                "ratelimit_{$sid}_creditperaction"
            );
            $storedcredit = (is_object($storedcredit) || is_array($storedcredit)) ? (array) $storedcredit : [];

            $data->credit[$sid] = [];
            foreach (\aiprovider_datacurso\provider::get_actions_for_service($sid) as $action) {
                $key = $action['key'];
                $data->credit[$sid][$key] = isset($storedcredit[$key]) ? (int) $storedcredit[$key] : (int) $action['default'];
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
     * Whether the current user may change the provider configuration (AIP-SEC-003).
     *
     * Viewing the reports (aiprovider/datacurso:viewreports) is not enough: it takes
     * aiprovider/datacurso:manageconfig, or moodle/site:config as before.
     *
     * @return bool
     */
    public static function can_manage(): bool {
        return plugin_settings::can_manage();
    }

    /**
     * Stop unless the current user may change the provider configuration.
     *
     * @return void
     * @throws \required_capability_exception When the user may not.
     */
    public static function require_manage_capability(): void {
        plugin_settings::require_manage_capability();
    }

    /**
     * Save the submitted configuration for the tenant of the current user.
     *
     * The capability is checked here as well as on the page, so the write path is guarded on its own.
     * The values go to the tenant of the current user only: nobody changes another tenant from here.
     *
     * @param \stdClass $data Submitted form data.
     * @return int Tenant the configuration was saved for.
     * @throws \required_capability_exception When the user may not change the configuration.
     */
    public static function save(\stdClass $data): int {
        global $USER;

        self::require_manage_capability();

        $tenantid = tenant_resolver::get_tenant_id((int) $USER->id);
        $previouslicence = tenant_config::get_stored('aiprovider_datacurso', $tenantid, 'licensekey');

        $values = clone $data;
        if (property_exists($values, provider_status::CONFIG_NAME)) {
            provider_status::set_tenant_enabled($tenantid, !empty($values->{provider_status::CONFIG_NAME}));
            unset($values->{provider_status::CONFIG_NAME});
        }
        tenant_config::save_from_form('aiprovider_datacurso', $tenantid, $values);

        // Credits per action arrive as a nested {service: {key: value}} array, so they are
        // saved explicitly here (as one JSON map per service) instead of through the generic
        // save_from_form(), which would otherwise flatten the map and lose its keys.
        if (isset($data->credit)) {
            foreach (array_column(\aiprovider_datacurso\provider::get_services(), 'id') as $sid) {
                $validkeys = array_column(\aiprovider_datacurso\provider::get_actions_for_service($sid), 'key');
                $submitted = (array) ($data->credit[$sid] ?? []);

                $map = [];
                foreach ($validkeys as $key) {
                    if (isset($submitted[$key])) {
                        $map[$key] = max(0, (int) $submitted[$key]);
                    }
                }

                tenant_config::set('aiprovider_datacurso', $tenantid, "ratelimit_{$sid}_creditperaction", json_encode($map));
            }
        }

        // The licence decides the region that serves this tenant: drop the region kept for the
        // previous licence so the next request resolves it for the new one.
        if (trim((string) ($data->licensekey ?? '')) !== trim((string) $previouslicence)) {
            license_region::forget_for_tenant($tenantid);
        }

        return $tenantid;
    }

    /**
     * Required for dynamic submissions.
     */
    public function set_data_for_dynamic_submission(): void {
         $this->set_data($this->get_initial_data());
    }
}
