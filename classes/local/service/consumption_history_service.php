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

defined('MOODLE_INTERNAL') || die();

use aiprovider_datacurso\httpclient\datacurso_api;

/**
 * Service class for retrieving consumption history with filtering and pagination.
 *
 * @package    aiprovider_datacurso
 * @category   service
 */
class consumption_history_service {
    /**
     * Get consumption history with pagination.
     *
     * @param int $page Page number.
     * @param int $limit Results per page.
     * @param int|null $userid User ID filter.
     * @param string|null $service Service ID filter.
     * @param string|null $action Action name filter.
     * @param string|null $fromdate Start date filter.
     * @param string|null $todate End date filter.
     * @param string|null $sort Field to order by.
     * @param string|null $sortdir Order direction (asc or desc).
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
        ?string $sortdir = null
    ): array {
        global $DB;

        $page = max(1, (int)$page);
        $limit = max(1, (int)$limit);

        $client = new datacurso_api();

        $queryparams = [
            'page' => $page,
            'limit' => $limit,
            'userid' => $userid,
            'servicio' => $service,
            'accion' => $action,
            'fecha_desde' => $fromdate,
            'fecha_hasta' => $todate,
            'shor' => $sort,
            'shordir' => $sortdir,
        ];

        try {
            $response = $client->get('/tokens/historial-consumos', $queryparams);

            if (isset($response['status']) && $response['status'] === 'success') {
                $users = $response['usuarios'] ?? [];
                $consumptions = [];

                $actions = \aiprovider_datacurso\provider::get_actions();
                $actionmap = [];

                $services = \aiprovider_datacurso\provider::get_services();
                $servicesmap = [];

                foreach ($services as $s) {
                    $servicesmap[$s['id']] = $s['name'];
                }

                foreach ($actions as $a) {
                    $actionmap[$a['id']] = $a['name'];
                }

                $userids = [];
                foreach ($users as $user) {
                    if (!empty($user['consumos'])) {
                        foreach ($user['consumos'] as $consumption) {
                            $userid = $consumption['userid'] ?? 0;
                            if ($userid > 0) {
                                $userids[$userid] = $userid;
                            }
                        }
                    }
                }

                $moodleusers = [];
                if (!empty($userids)) {
                    [$insql, $inparams] = $DB->get_in_or_equal(array_values($userids));
                    $moodleusers = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename');
                }

                foreach ($users as $user) {
                    if (!empty($user['consumos'])) {
                        foreach ($user['consumos'] as $consumption) {
                            $actionid = $consumption['accion'] ?? '';
                            $actionname = $actionmap[$actionid] ?? $actionid;
                            $serviceid = $consumption['id_servicio'] ?? '';
                            $servicename = $servicesmap[$serviceid] ?? $serviceid;
                            $userid = $consumption['userid'] ?? 0;

                            $username = '-';
                            if ($userid > 0 && isset($moodleusers[$userid])) {
                                $userobj = $moodleusers[$userid];
                                $username = fullname($userobj);
                            }

                            $consumptions[] = [
                                'id_consumption' => $consumption['id_consumo'] ?? 0,
                                'userid' => $consumption['userid'] ?? ($consumption['id_usuario'] ?? 0),
                                'username' => $username,
                                'action' => $actionname,
                                'id_service' => $servicename,
                                'cant_tokens' => $consumption['cantidad_tokens'] ?? 0,
                                'balance' => $consumption['saldo_restante'] ?? 0,
                                'date' => $consumption['created_at'] ?? '',
                            ];
                        }
                    }
                }

                $pagination = $response['pagination'] ?? $response['paginacion'] ?? [];

                return [
                    'status' => 'success',
                    'consumption' => $consumptions,
                    'pagination' => [
                        'current_page' => $pagination['current_page'] ?? $pagination['pagina_actual'] ?? $page,
                        'limit' => $pagination['limit'] ?? $pagination['limite'] ?? $limit,
                        'total' => $pagination['total'] ?? count($consumptions),
                        'total_pages' => $pagination['total_pages'] ?? $pagination['total_paginas'] ?? 1,
                    ],
                ];
            }

            return [
                'status' => 'error',
                'message' => get_string('nodata', 'aiprovider_datacurso'),
                'consumption' => [],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'consumption' => [],
            ];
        }
    }
}
