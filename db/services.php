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
 * External functions and service declaration for Datacurso Provider
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    aiprovider_datacurso
 * @category   webservice
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'aiprovider_datacurso_get_credits_balance' => [
        'classname'   => 'aiprovider_datacurso\external\get_credits_balance',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Gets current available credits balance',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',

    ],
    'aiprovider_datacurso_get_consumption_history' => [
        'classname'   => 'aiprovider_datacurso\external\get_consumption_history',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Gets the token consumption history from the external API',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',
    ],
    'aiprovider_datacurso_get_all_consumption' => [
        'classname'   => 'aiprovider_datacurso\external\get_all_consumption',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Gets the complete history of token consumption from the external API',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',
    ],
    'aiprovider_datacurso_get_services' => [
        'classname'   => 'aiprovider_datacurso\external\get_services',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get the list of available AI services for filtering.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',
    ],
    'aiprovider_datacurso_get_actions' => [
        'classname'   => 'aiprovider_datacurso\external\get_actions',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get the list of available AI actions.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',
    ],
    'aiprovider_datacurso_get_users' => [
        'classname'   => 'aiprovider_datacurso\external\get_users',
        'methodname'  => 'execute',
        'classpath'   => '',
        'description' => 'Get the list of users for filtering.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'aiprovider/datacurso:viewreports',
    ],
];
