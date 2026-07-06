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

use aiprovider_datacurso\provider;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Per-plugin rate-limit configuration form (native moodleform).
 *
 * Renders, per service, the credit limit within a time window plus the estimated credits per
 * sub-action. Reads from / writes to the same config keys as before
 * (`ratelimit_{service}_enable|limit|window|creditperaction`), so the enforcement side is unchanged.
 *
 * Element names use bracket notation keyed by service id (and sub-action key) to avoid ambiguous
 * parsing, e.g. `enable[local_coursegen]`, `credit[local_coursegen][course_image]`.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_form extends \moodleform {

    /** @var string[] Allowed window units. */
    private const UNITS = ['minutes', 'hours', 'days'];

    /**
     * Form definition: one grouped section per service.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $services = provider::get_services();
        \core_collator::asort_array_of_arrays_by_key($services, 'name');

        $unitoptions = [];
        foreach (self::UNITS as $unit) {
            $unitoptions[$unit] = get_string($unit, 'aiprovider_datacurso');
        }

        $mform->addElement('html', \html_writer::tag(
            'p',
            get_string('config_desc', 'aiprovider_datacurso'),
            ['class' => 'text-muted']
        ));

        foreach ($services as $service) {
            $sid = $service['id'];

            $mform->addElement('header', "head_{$sid}", format_string($service['name']));
            $mform->setExpanded("head_{$sid}", true);

            // Enable checkbox.
            $mform->addElement('advcheckbox', "enable[{$sid}]",
                get_string('ratelimit_enable', 'aiprovider_datacurso'));
            $mform->setType("enable[{$sid}]", PARAM_INT);
            $mform->addHelpButton("enable[{$sid}]", 'ratelimit_enable', 'aiprovider_datacurso');

            // Credit limit per window.
            $mform->addElement('text', "limit[{$sid}]",
                get_string('ratelimit_limit', 'aiprovider_datacurso'), ['size' => 8]);
            $mform->setType("limit[{$sid}]", PARAM_INT);
            $mform->addHelpButton("limit[{$sid}]", 'ratelimit_limit', 'aiprovider_datacurso');
            $mform->hideIf("limit[{$sid}]", "enable[{$sid}]");

            // Window: value + unit, side by side.
            $group = [
                $mform->createElement('text', "windowvalue[{$sid}]", '', ['size' => 6]),
                $mform->createElement('select', "windowunit[{$sid}]", '', $unitoptions),
            ];
            $mform->addGroup($group, "windowgroup_{$sid}",
                get_string('ratelimit_window', 'aiprovider_datacurso'), ' ', false);
            $mform->setType("windowvalue[{$sid}]", PARAM_INT);
            $mform->addHelpButton("windowgroup_{$sid}", 'ratelimit_window', 'aiprovider_datacurso');
            $mform->hideIf("windowgroup_{$sid}", "enable[{$sid}]");

            // Credits per action: bold title in the label column, description alongside (no empty column).
            $mform->addElement('static', "cpahead_{$sid}",
                \html_writer::tag('strong', get_string('ratelimit_creditperaction', 'aiprovider_datacurso'),
                    ['class' => 'h5']),
                \html_writer::tag('span',
                    get_string('ratelimit_creditperaction_desc', 'aiprovider_datacurso'),
                    ['class' => 'text-muted']));
            $mform->hideIf("cpahead_{$sid}", "enable[{$sid}]");

            foreach (provider::get_actions_for_service($sid) as $action) {
                $key = $action['key'];
                $mform->addElement('text', "credit[{$sid}][{$key}]", (string) $action['name'], ['size' => 8]);
                $mform->setType("credit[{$sid}][{$key}]", PARAM_INT);
                $mform->addHelpButton("credit[{$sid}][{$key}]", 'ratelimit_creditperaction', 'aiprovider_datacurso');
                $mform->hideIf("credit[{$sid}][{$key}]", "enable[{$sid}]");
            }
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Validate numeric ranges.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        foreach (($data['limit'] ?? []) as $sid => $value) {
            if ((int) $value < 0) {
                $errors["limit[{$sid}]"] = get_string('err_positive', 'form');
            }
        }
        foreach (($data['windowvalue'] ?? []) as $sid => $value) {
            if ((int) $value < 1) {
                $errors["windowgroup_{$sid}"] = get_string('err_positive', 'form');
            }
        }
        foreach (($data['credit'] ?? []) as $sid => $map) {
            foreach ((array) $map as $key => $value) {
                if ((int) $value < 0) {
                    $errors["credit[{$sid}][{$key}]"] = get_string('err_positive', 'form');
                }
            }
        }

        return $errors;
    }

    /**
     * Build the data object to prefill the form from the stored configuration.
     *
     * Mirrors the previous config_page reading: catalog defaults merged with the stored JSON map,
     * plus the {value, unit} window.
     *
     * @return array
     */
    public static function current_data(): array {
        $enable = [];
        $limit = [];
        $windowvalue = [];
        $windowunit = [];
        $credit = [];

        foreach (provider::get_services() as $service) {
            $sid = $service['id'];

            $enable[$sid] = (int) get_config('aiprovider_datacurso', "ratelimit_{$sid}_enable") === 1 ? 1 : 0;

            $storedlimit = get_config('aiprovider_datacurso', "ratelimit_{$sid}_limit");
            $limit[$sid] = ($storedlimit === false || $storedlimit === '') ? 10 : (int) $storedlimit;

            // Credits per action: stored JSON map, falling back to the catalog default per key.
            $stored = json_decode((string) get_config('aiprovider_datacurso', "ratelimit_{$sid}_creditperaction"), true);
            $stored = is_array($stored) ? $stored : [];
            $credit[$sid] = [];
            foreach (provider::get_actions_for_service($sid) as $action) {
                $key = $action['key'];
                $credit[$sid][$key] = isset($stored[$key]) ? (int) $stored[$key] : (int) $action['default'];
            }

            // Window {value, unit}.
            $window = json_decode((string) get_config('aiprovider_datacurso', "ratelimit_{$sid}_window"), true);
            $windowvalue[$sid] = is_array($window) && (int) ($window['value'] ?? 0) > 0 ? (int) $window['value'] : 1;
            $windowunit[$sid] = is_array($window) && in_array(($window['unit'] ?? ''), self::UNITS, true)
                ? $window['unit'] : 'hours';
        }

        return [
            'enable' => $enable,
            'limit' => $limit,
            'windowvalue' => $windowvalue,
            'windowunit' => $windowunit,
            'credit' => $credit,
        ];
    }

    /**
     * Persist the submitted configuration.
     *
     * Ports the validation and set_config calls from the former ratelimit_config_api::save_config,
     * keeping the exact same config keys and JSON encodings.
     *
     * @param \stdClass $data Submitted data from get_data().
     */
    public static function save(\stdClass $data): void {
        $validids = array_column(provider::get_services(), 'id');

        foreach ($validids as $sid) {
            $enable = !empty($data->enable[$sid]) ? 1 : 0;
            $limit = max(0, (int) ($data->limit[$sid] ?? 0));

            $windowvalue = (int) ($data->windowvalue[$sid] ?? 0);
            $windowvalue = $windowvalue > 0 ? $windowvalue : 1;
            $windowunit = in_array($data->windowunit[$sid] ?? '', self::UNITS, true)
                ? $data->windowunit[$sid] : 'hours';

            // Credits per action → JSON map, keeping only keys valid for this service.
            $validkeys = array_column(provider::get_actions_for_service($sid), 'key');
            $submitted = (array) ($data->credit[$sid] ?? []);
            $map = [];
            foreach ($validkeys as $key) {
                if (isset($submitted[$key])) {
                    $map[$key] = max(0, (int) $submitted[$key]);
                }
            }

            set_config("ratelimit_{$sid}_enable", $enable, 'aiprovider_datacurso');
            set_config("ratelimit_{$sid}_limit", $limit, 'aiprovider_datacurso');
            set_config("ratelimit_{$sid}_creditperaction", json_encode($map), 'aiprovider_datacurso');
            set_config(
                "ratelimit_{$sid}_window",
                json_encode(['value' => $windowvalue, 'unit' => $windowunit]),
                'aiprovider_datacurso'
            );
        }
    }
}
