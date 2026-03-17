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
 * Service class for retrieving all consumption records from Datacurso API.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption_service {
    /** @var int Number of records per page. */
    private const LIMIT_PER_PAGE = 500;

    /**
     * Get all consumption records with pagination.
     *
     * @param string|null $service Service filter.
     * @param string|null $action Action filter.
     * @param int|null $userid User filter.
     * @param string|null $fromdate Start date (YYYY-MM-DD).
     * @param string|null $todate End date (YYYY-MM-DD).
     * @return array
     */
    public static function get_all_consumption(
        ?string $service = null,
        ?string $action = null,
        ?int $userid = null,
        ?string $fromdate = null,
        ?string $todate = null
    ): array {
        $client = new datacurso_api();

        $queryparams = [
            'page' => 1,
            'limit' => self::LIMIT_PER_PAGE,
        ];

        if (!empty($service) && $service !== 'all') {
            $queryparams['servicio'] = $service;
        }
        if (!empty($action) && $action !== 'all') {
            $queryparams['accion'] = $action;
        }
        if (!empty($userid)) {
            $queryparams['userid'] = $userid;
        }
        if (!empty($fromdate)) {
            $queryparams['fecha_desde'] = $fromdate;
        }
        if (!empty($todate)) {
            $queryparams['fecha_hasta'] = $todate;
        }

        $firstresponse = $client->get('/tokens/historial-consumos', $queryparams);

        if (empty($firstresponse) || $firstresponse['status'] !== 'success') {
            return [
                'status' => 'error',
                'message' => get_string('errorinitinformation', 'aiprovider_datacurso'),
                'total' => 0,
                'consumption' => [],
            ];
        }

        $actions = \aiprovider_datacurso\provider::get_actions();
        $actionmap = [];
        foreach ($actions as $actionitem) {
            $actionmap[$actionitem['id']] = $actionitem['name'];
        }

        $pagination = $firstresponse['paginacion'] ?? [];
        $totalrecords = (int)($pagination['total'] ?? 0);
        $limitperpage = self::LIMIT_PER_PAGE;
        $totalpages = ceil($totalrecords / $limitperpage);

        $allconsumptions = [];

        $userdata = $firstresponse['usuarios'][0] ?? null;
        $consumptions = $userdata['consumos'] ?? [];
        foreach ($consumptions as $item) {
            $rawaction = (string)($item['accion'] ?? '');
            $translatedaction = $actionmap[$rawaction] ?? $rawaction;
            $allconsumptions[] = [
                'id_consumption' => (int)($item['id_consumo'] ?? 0),
                'action' => $translatedaction,
                'id_service' => (string)($item['id_servicio'] ?? ''),
                'userid' => isset($item['userid']) ? (int)$item['userid'] : null,
                'cant_tokens' => $item['cantidad_tokens'] ?? 0,
                'balance' => $item['saldo_restante'] ?? 0,
                'date' => (string)($item['fecha'] ?? ''),
                'created_at' => (string)($item['created_at'] ?? ''),
            ];
        }

        for ($page = 2; $page <= $totalpages; $page++) {
            $queryparams['page'] = $page;
            $queryparams['limit'] = $limitperpage;

            $response = $client->get('/tokens/historial-consumos', $queryparams);

            if (empty($response) || $response['status'] !== 'success') {
                continue;
            }

            $userdata = $response['usuarios'][0] ?? null;
            $consumptions = $userdata['consumos'] ?? [];

            foreach ($consumptions as $item) {
                $rawaction = (string)($item['accion'] ?? '');
                $translatedaction = $actionmap[$rawaction] ?? $rawaction;
                $allconsumptions[] = [
                    'id_consumption' => (int)($item['id_consumo'] ?? 0),
                    'action' => $translatedaction,
                    'id_service' => (string)($item['id_servicio'] ?? ''),
                    'userid' => isset($item['userid']) ? (int)$item['userid'] : null,
                    'cant_tokens' => $item['cantidad_tokens'] ?? 0,
                    'balance' => $item['saldo_restante'] ?? 0,
                    'date' => (string)($item['fecha'] ?? ''),
                    'created_at' => (string)($item['created_at'] ?? ''),
                ];
            }
        }

        return [
            'status' => 'success',
            'total' => $totalrecords,
            'consumption' => $allconsumptions,
        ];
    }
}
