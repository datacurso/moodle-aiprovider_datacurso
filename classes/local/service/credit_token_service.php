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

namespace aiprovider_datacurso\local\service;
use aiprovider_datacurso\httpclient\datacurso_api;

/**
 * Service class for managing credit tokens.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class credit_token_service {
    /**
     * Get the available credits balance for assignment.
     *
     * @return array
     */
    public static function get_credits_balance(): array {
        try {
            $client = new datacurso_api();
            $response = $client->get('/tokens/saldo');
            if (($response['status'] ?? 'error') !== 'success') {
                return [
                    'status' => 'error',
                    'balance' => 0,
                    'message' => get_string('errorgetbalancecredits', 'aiprovider_datacurso'),
                ];
            }

            return [
                'status' => 'success',
                'balance' => (int)($response['saldo_actual'] ?? 0),
                'message' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'balance' => 0,
                'message' => get_string('errorgetbalancecredits', 'aiprovider_datacurso'),
            ];
        }
    }
}
