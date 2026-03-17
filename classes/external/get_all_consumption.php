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
 * External web service to fetch all Datacurso API consumption history.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_all_consumption extends external_api {

    /**
     * Defines input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'service' => new external_value(PARAM_TEXT, 'Service filter', VALUE_OPTIONAL),
            'action' => new external_value(PARAM_TEXT, 'Action filter', VALUE_OPTIONAL),
            'userid' => new external_value(PARAM_INT, 'User filter', VALUE_OPTIONAL),
            'fromdate' => new external_value(PARAM_RAW, 'Start date (YYYY-MM-DD)', VALUE_OPTIONAL),
            'todate' => new external_value(PARAM_RAW, 'End date (YYYY-MM-DD)', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Executes the web service to retrieve all consumption records.
     *
     * @param string|null $service Service filter.
     * @param string|null $action Action filter.
     * @param int|null $userid User filter.
     * @param string|null $fromdate Start date (YYYY-MM-DD).
     * @param string|null $todate End date (YYYY-MM-DD).
     * @return array Returns the status, total, and list of consumption records.
     */
    public static function execute(
        ?string $service = null,
        ?string $action = null,
        ?int $userid = null,
        ?string $fromdate = null,
        ?string $todate = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'service' => $service,
            'action' => $action,
            'userid' => $userid,
            'fromdate' => $fromdate,
            'todate' => $todate,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/datacurso:viewreports', $context);

        return consumption_service::get_all_consumption(
            $params['service'],
            $params['action'],
            $params['userid'],
            $params['fromdate'],
            $params['todate']
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
            'total' => new external_value(PARAM_INT, 'Total number of consumption records found'),
            'consumption' => new external_multiple_structure(
                new external_single_structure([
                    'id_consumption' => new external_value(PARAM_INT, 'Consumption record ID'),
                    'action' => new external_value(PARAM_TEXT, 'Performed action'),
                    'id_service' => new external_value(PARAM_TEXT, 'Used service'),
                    'userid' => new external_value(PARAM_INT, 'Moodle user ID', VALUE_OPTIONAL),
                    'cant_tokens' => new external_value(PARAM_FLOAT, 'Number of tokens consumed'),
                    'balance' => new external_value(PARAM_FLOAT, 'Remaining token balance'),
                    'date' => new external_value(PARAM_RAW, 'Consumption date (YYYY-MM-DD)'),
                    'created_at' => new external_value(PARAM_RAW, 'Record creation date in the API'),
                ])
            ),
        ]);
    }
}
