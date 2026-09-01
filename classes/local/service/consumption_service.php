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

/**
 * Service class providing consumption data for the report charts.
 *
 * Reads from the local mirror table {aiprovider_datacurso_consumption} (kept up to date by
 * {@see \aiprovider_datacurso\local\sync\consumption_sync}) instead of hitting the external API,
 * returning the row shape the report charts expect.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption_service {
    /** @var string Local mirror table. */
    private const TABLE = 'aiprovider_datacurso_consumption';

    /**
     * Get consumption credit totals aggregated by a single dimension, for the report charts.
     *
     * Only the aggregated buckets are returned (not the raw rows), so the charts receive a small
     * payload and do no client-side grouping.
     *
     * @param string $groupby One of 'month', 'day', 'action', 'service'.
     * @param string|null $service Service filter.
     * @param string|null $action Action filter.
     * @param int|null $userid User filter.
     * @param string|null $fromdate Start date (YYYY-MM-DD).
     * @param string|null $todate End date (YYYY-MM-DD).
     * @return array
     */
    public static function get_summary(
        string $groupby,
        ?string $service = null,
        ?string $action = null,
        ?int $userid = null,
        ?string $fromdate = null,
        ?string $todate = null
    ): array {
        global $DB;

        $groupby = in_array($groupby, ['month', 'day', 'action', 'service'], true) ? $groupby : 'month';

        [$wheresql, $params] = self::build_conditions($service, $action, $userid, $fromdate, $todate);

        $actionmap = [];
        foreach (\aiprovider_datacurso\provider::get_actions() as $item) {
            $actionmap[$item['id']] = $item['name'];
        }
        $servicemap = [];
        foreach (\aiprovider_datacurso\provider::get_services() as $item) {
            $servicemap[$item['id']] = $item['name'];
        }

        $recordset = $DB->get_recordset_select(
            self::TABLE,
            $wheresql,
            $params,
            'timecreated ASC',
            'id, service, action, credits, timecreated'
        );

        $buckets = [];
        $total = 0.0;
        foreach ($recordset as $record) {
            $credits = (float) $record->credits;
            $total += $credits;

            switch ($groupby) {
                case 'day':
                    $label = date('Y-m-d', $record->timecreated);
                    break;
                case 'action':
                    $label = $actionmap[$record->action] ?? $record->action;
                    break;
                case 'service':
                    $label = $servicemap[$record->service] ?? $record->service;
                    break;
                default:
                    $label = date('Y-m', $record->timecreated);
                    break;
            }

            $buckets[$label] = ($buckets[$label] ?? 0) + $credits;
        }
        $recordset->close();

        // Chronological order for time buckets; largest first for categorical ones.
        if ($groupby === 'month' || $groupby === 'day') {
            ksort($buckets);
        } else {
            arsort($buckets);
        }

        $summary = [];
        foreach ($buckets as $label => $sum) {
            $summary[] = ['label' => (string) $label, 'total' => (float) $sum];
        }

        return [
            'status' => 'success',
            'total' => $total,
            'summary' => $summary,
        ];
    }

    /**
     * Build the SQL WHERE clause and parameters for the consumption filters.
     *
     * @param string|null $service
     * @param string|null $action
     * @param int|null $userid
     * @param string|null $fromdate
     * @param string|null $todate
     * @return array{0: string, 1: array}
     */
    private static function build_conditions(
        ?string $service,
        ?string $action,
        ?int $userid,
        ?string $fromdate,
        ?string $todate
    ): array {
        $conditions = [];
        $params = [];

        if (!empty($service) && $service !== 'all') {
            $conditions[] = 'service = :service';
            $params['service'] = $service;
        }
        if (!empty($action) && $action !== 'all') {
            $conditions[] = 'action = :action';
            $params['action'] = $action;
        }
        if (!empty($userid)) {
            $conditions[] = 'userid = :userid';
            $params['userid'] = (int) $userid;
        }
        if (!empty($fromdate) && ($from = strtotime($fromdate . ' 00:00:00')) !== false) {
            $conditions[] = 'timecreated >= :fromdate';
            $params['fromdate'] = $from;
        }
        if (!empty($todate) && ($to = strtotime($todate . ' 23:59:59')) !== false) {
            $conditions[] = 'timecreated <= :todate';
            $params['todate'] = $to;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
