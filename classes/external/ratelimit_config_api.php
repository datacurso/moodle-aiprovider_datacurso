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

namespace aiprovider_datacurso\external;

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use aiprovider_datacurso\provider;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * AJAX external functions to save the per-plugin rate-limit configuration.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2025 Datacurso
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ratelimit_config_api extends external_api {
    /** @var string[] Allowed window units. */
    private const UNITS = ['minutes', 'hours', 'days'];

    /**
     * Parameters for save_config.
     *
     * @return external_function_parameters
     */
    public static function save_config_parameters(): external_function_parameters {
        return new external_function_parameters([
            'services' => new external_multiple_structure(
                new external_single_structure([
                    'sid' => new external_value(PARAM_ALPHANUMEXT, 'Service id (e.g. local_assign_ai)'),
                    'enable' => new external_value(PARAM_INT, 'Rate limit enabled (0/1)'),
                    'limit' => new external_value(PARAM_INT, 'Credit limit per window'),
                    'windowvalue' => new external_value(PARAM_INT, 'Window duration value'),
                    'windowunit' => new external_value(PARAM_ALPHA, 'Window unit: minutes|hours|days'),
                    'creditsperaction' => new external_multiple_structure(
                        new external_single_structure([
                            'key' => new external_value(PARAM_ALPHANUMEXT, 'Sub-action key'),
                            'value' => new external_value(PARAM_INT, 'Estimated max credits for this sub-action'),
                        ]),
                        'Estimated max credits per sub-action'
                    ),
                ]),
                'Per-plugin rate limit configuration rows'
            ),
        ]);
    }

    /**
     * Persist the per-plugin rate-limit configuration.
     *
     * @param array $services Configuration rows.
     * @return array Result with a status flag.
     */
    public static function save_config(array $services): array {
        $params = self::validate_parameters(self::save_config_parameters(), ['services' => $services]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/datacurso:configurews', $context);

        // Valid service ids (avoid writing arbitrary config keys).
        $validids = array_column(provider::get_services(), 'id');

        foreach ($params['services'] as $service) {
            $sid = $service['sid'];
            if (!in_array($sid, $validids, true)) {
                continue;
            }

            $enable = !empty($service['enable']) ? 1 : 0;
            $limit = max(0, (int) $service['limit']);

            $windowvalue = (int) $service['windowvalue'];
            $windowvalue = $windowvalue > 0 ? $windowvalue : 1;
            $windowunit = in_array($service['windowunit'], self::UNITS, true) ? $service['windowunit'] : 'hours';

            // Credits per action → JSON map {key: value}, keeping only keys valid for this service.
            $validkeys = array_column(provider::get_actions_for_service($sid), 'key');
            $map = [];
            foreach ($service['creditsperaction'] as $entry) {
                if (in_array($entry['key'], $validkeys, true)) {
                    $map[$entry['key']] = max(0, (int) $entry['value']);
                }
            }

            set_config("ratelimit_{$sid}_enable", $enable, 'aiprovider_datacurso');
            set_config("ratelimit_{$sid}_limit", $limit, 'aiprovider_datacurso');
            set_config("ratelimit_{$sid}_creditperaction", json_encode($map), 'aiprovider_datacurso');
            // Window kept as the same {value, unit} JSON the ratelimiter already reads.
            set_config(
                "ratelimit_{$sid}_window",
                json_encode(['value' => $windowvalue, 'unit' => $windowunit]),
                'aiprovider_datacurso'
            );
        }

        return ['status' => 'success'];
    }

    /**
     * Return structure for save_config.
     *
     * @return external_single_structure
     */
    public static function save_config_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status'),
        ]);
    }
}
