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
 * Web service for retrieving token consumption history.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use aiprovider_datacurso\local\service\consumption_history_service;

/**
 * External web service to fetch Datacurso API consumption history.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_consumption_history extends \external_api {
    /**
     * Define parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 1),
            'limit' => new external_value(PARAM_INT, 'Results per page', VALUE_DEFAULT, 10),
            'userid' => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0),
            'service' => new external_value(PARAM_TEXT, 'Service ID', VALUE_DEFAULT, ''),
            'action' => new external_value(PARAM_TEXT, 'Action name', VALUE_DEFAULT, ''),
            'fromdate' => new external_value(PARAM_TEXT, 'Start date', VALUE_DEFAULT, ''),
            'todate' => new external_value(PARAM_TEXT, 'End date', VALUE_DEFAULT, ''),
            'shor' => new external_value(PARAM_TEXT, 'Field to order', VALUE_DEFAULT, ''),
            'shordir' => new external_value(PARAM_TEXT, 'Order direction (asc or desc)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute function.
     *
     * @param int $page
     * @param int $limit
     * @param int|null $userid
     * @param string|null $service
     * @param string|null $action
     * @param string|null $fromdate
     * @param string|null $todate
     * @param string|null $shor
     * @param string|null $shordir
     * @return array
     */
    public static function execute(
        $page = 1,
        $limit = 10,
        $userid = null,
        $service = null,
        $action = null,
        $fromdate = null,
        $todate = null,
        $shor = null,
        $shordir = null
    ) {
        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'limit' => $limit,
            'userid' => $userid,
            'service' => $service,
            'action' => $action,
            'fromdate' => $fromdate,
            'todate' => $todate,
            'shor' => $shor,
            'shordir' => $shordir,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/datacurso:viewreports', $context);

        return consumption_history_service::get_consumption_history(
            (int) $params['page'],
            (int) $params['limit'],
            !empty($params['userid']) ? (int) $params['userid'] : null,
            !empty($params['service']) ? $params['service'] : null,
            !empty($params['action']) ? $params['action'] : null,
            !empty($params['fromdate']) ? $params['fromdate'] : null,
            !empty($params['todate']) ? $params['todate'] : null,
            !empty($params['shor']) ? $params['shor'] : null,
            !empty($params['shordir']) ? $params['shordir'] : null
        );
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Operation status'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'consumption' => new external_multiple_structure(
                new external_single_structure([
                    'id_consumption' => new external_value(PARAM_INT, 'Consumption ID'),
                    'userid' => new external_value(PARAM_INT, 'User ID'),
                    'username' => new external_value(PARAM_TEXT, 'User full name'),
                    'action' => new external_value(PARAM_TEXT, 'Action performed'),
                    'id_service' => new external_value(PARAM_TEXT, 'Service identifier'),
                    'cant_tokens' => new external_value(PARAM_FLOAT, 'Tokens used'),
                    'balance' => new external_value(PARAM_FLOAT, 'Remaining balance'),
                    'date' => new external_value(PARAM_TEXT, 'Consumption date'),
                ])
            ),
            'pagination' => new external_single_structure([
                'current_page' => new external_value(PARAM_INT, 'Current page'),
                'limit' => new external_value(PARAM_INT, 'Limit per page'),
                'total' => new external_value(PARAM_INT, 'Total records'),
                'total_pages' => new external_value(PARAM_INT, 'Total pages'),
            ], 'Pagination information', VALUE_OPTIONAL),
        ]);
    }
}
