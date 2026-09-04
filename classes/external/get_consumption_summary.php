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

namespace aiprovider_datacurso\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use aiprovider_datacurso\local\service\consumption_service;

/**
 * External web service returning aggregated consumption totals for the report charts.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_consumption_summary extends external_api {
    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'groupby' => new external_value(PARAM_ALPHA, 'Aggregation dimension: month, day, action or service'),
            'service' => new external_value(PARAM_TEXT, 'Service filter', VALUE_DEFAULT, ''),
            'action' => new external_value(PARAM_TEXT, 'Action filter', VALUE_DEFAULT, ''),
            'userid' => new external_value(PARAM_INT, 'User filter', VALUE_DEFAULT, 0),
            'fromdate' => new external_value(PARAM_RAW, 'Start date (YYYY-MM-DD)', VALUE_DEFAULT, ''),
            'todate' => new external_value(PARAM_RAW, 'End date (YYYY-MM-DD)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Return the aggregated consumption totals for the requested dimension.
     *
     * @param string $groupby Aggregation dimension.
     * @param string $service Service filter.
     * @param string $action Action filter.
     * @param int $userid User filter.
     * @param string $fromdate Start date.
     * @param string $todate End date.
     * @return array
     */
    public static function execute(
        string $groupby,
        string $service = '',
        string $action = '',
        int $userid = 0,
        string $fromdate = '',
        string $todate = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'groupby' => $groupby,
            'service' => $service,
            'action' => $action,
            'userid' => $userid,
            'fromdate' => $fromdate,
            'todate' => $todate,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/datacurso:viewreports', $context);

        return consumption_service::get_summary(
            $params['groupby'],
            $params['service'] !== '' ? $params['service'] : null,
            $params['action'] !== '' ? $params['action'] : null,
            !empty($params['userid']) ? (int) $params['userid'] : null,
            $params['fromdate'] !== '' ? $params['fromdate'] : null,
            $params['todate'] !== '' ? $params['todate'] : null
        );
    }

    /**
     * Defines the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Request status (success/error)'),
            'total' => new external_value(PARAM_FLOAT, 'Sum of credits over the filtered set'),
            'summary' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_RAW, 'Group label'),
                    'total' => new external_value(PARAM_FLOAT, 'Credits consumed for this group'),
                ])
            ),
        ]);
    }
}
