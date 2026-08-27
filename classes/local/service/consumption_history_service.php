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
 * Service class for retrieving consumption history with filtering and pagination.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption_history_service {
    /**
     * Get consumption history with pagination.
     *
     * A non-positive $limit means "bring everything" (used by the CSV export): the pages
     * are walked server-side and every row is returned in a single, unpaginated response.
     *
     * @param int $page Page number.
     * @param int $limit Results per page. <= 0 walks and returns every page.
     * @param int|null $userid User ID filter.
     * @param string|null $service Service ID filter.
     * @param string|null $action Action name filter.
     * @param string|null $fromdate Start date filter.
     * @param string|null $todate End date filter.
     * @param string|null $sort Field to order by.
     * @param string|null $sortdir Order direction (asc or desc).
     * @param datacurso_api|null $client API client override, used by tests to fake the
     *                                   remote responses instead of making a real request.
     * @return array
     */
    public static function get_consumption_history(
        int $page = 1,
        int $limit = 10,
        ?int $userid = null,
        ?string $service = null,
        ?string $action = null,
        ?string $fromdate = null,
        ?string $todate = null,
        ?string $sort = null,
        ?string $sortdir = null,
        ?datacurso_api $client = null
    ): array {
        global $DB;

        $fetchall = ((int)$limit <= 0);
        $page = max(1, (int)$page);
        $perpage = $fetchall ? 500 : max(1, (int)$limit);

        $client = $client ?? new datacurso_api();

        $queryparams = [
            'page' => $page,
            'limit' => $perpage,
            'userid' => $userid,
            'servicio' => $service,
            'accion' => $action,
            'fecha_desde' => $fromdate,
            'fecha_hasta' => $todate,
            'shor' => $sort,
            'shor_dir' => $sortdir,
        ];

        try {
            $response = $client->get('/tokens/historial-consumos', $queryparams);

            if (!isset($response['status']) || $response['status'] !== 'success') {
                return [
                    'status' => 'error',
                    'message' => get_string('nodata', 'aiprovider_datacurso'),
                    'consumption' => [],
                ];
            }

            $pagination = $response['pagination'] ?? $response['paginacion'] ?? [];
            $totalpages = (int)($pagination['total_pages'] ?? $pagination['total_paginas'] ?? 1);

            // Flatten the raw items, walking every remaining page when fetching all.
            $rawitems = self::extract_items($response);
            if ($fetchall && $totalpages > 1) {
                for ($p = 2; $p <= $totalpages; $p++) {
                    $queryparams['page'] = $p;
                    $pageresponse = $client->get('/tokens/historial-consumos', $queryparams);
                    if (isset($pageresponse['status']) && $pageresponse['status'] === 'success') {
                        $rawitems = array_merge($rawitems, self::extract_items($pageresponse));
                    }
                }
            }

            $actionmap = [];
            foreach (\aiprovider_datacurso\provider::get_actions() as $a) {
                $actionmap[$a['id']] = $a['name'];
            }

            $servicesmap = [];
            foreach (\aiprovider_datacurso\provider::get_services() as $s) {
                $servicesmap[$s['id']] = $s['name'];
            }

            $userids = [];
            foreach ($rawitems as $item) {
                $uid = (int)($item['userid'] ?? 0);
                if ($uid > 0) {
                    $userids[$uid] = $uid;
                }
            }

            $moodleusers = [];
            if (!empty($userids)) {
                [$insql, $inparams] = $DB->get_in_or_equal(array_values($userids));
                $userfields = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
                $moodleusers = $DB->get_records_select('user', "id $insql", $inparams, '', $userfields);
            }

            $consumptions = [];
            foreach ($rawitems as $item) {
                $actionid = $item['accion'] ?? '';
                $serviceid = $item['id_servicio'] ?? '';
                $uid = (int)($item['userid'] ?? 0);

                $username = '-';
                if ($uid > 0 && isset($moodleusers[$uid])) {
                    $username = fullname($moodleusers[$uid]);
                }

                $consumptions[] = [
                    'id_consumption' => $item['id_consumo'] ?? 0,
                    'userid' => $item['userid'] ?? ($item['id_usuario'] ?? 0),
                    'username' => $username,
                    'action' => $actionmap[$actionid] ?? $actionid,
                    'id_service' => $servicesmap[$serviceid] ?? $serviceid,
                    'cant_tokens' => $item['cantidad_tokens'] ?? 0,
                    'balance' => $item['saldo_restante'] ?? 0,
                    'date' => $item['created_at'] ?? '',
                ];
            }

            return [
                'status' => 'success',
                'consumption' => $consumptions,
                'pagination' => [
                    'current_page' => $fetchall ? 1 : ($pagination['current_page'] ?? $pagination['pagina_actual'] ?? $page),
                    'limit' => $fetchall ? count($consumptions) : $perpage,
                    'total' => $fetchall ? count($consumptions) : (int)($pagination['total'] ?? count($consumptions)),
                    'total_pages' => $fetchall ? 1 : $totalpages,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'consumption' => [],
            ];
        }
    }

    /**
     * Flatten the raw consumption items (across all users) from a historial-consumos response.
     *
     * @param array $response Decoded API response.
     * @return array<int, array> Raw consumption items.
     */
    private static function extract_items(array $response): array {
        $items = [];
        foreach (($response['usuarios'] ?? []) as $user) {
            foreach (($user['consumos'] ?? []) as $consumption) {
                $items[] = $consumption;
            }
        }
        return $items;
    }
}
