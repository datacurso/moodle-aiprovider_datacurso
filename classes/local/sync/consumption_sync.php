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

namespace aiprovider_datacurso\local\sync;

use aiprovider_datacurso\httpclient\datacurso_api;
use aiprovider_datacurso\local\tenant_config;
use aiprovider_datacurso\local\tenant_resolver;

/**
 * Keeps the local consumption mirror table up to date from the external Datacurso API.
 *
 * The consumption history lives in the external service, but Report Builder can only query a
 * local DB table. This service is called when the report page is opened: it pulls the records
 * created since the last synced one (or performs a full backfill on the first run) into
 * {aiprovider_datacurso_consumption}, so the report always renders fresh data without cron.
 *
 * On Workplace each tenant may hold its own licence, and the service answers with the history of
 * the licence the request is made with. So each record keeps the fingerprint of that licence, which
 * gives every licence its own watermark, and the tenant of the user who consumed, which is what the
 * report and the charts filter on.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Josue <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class consumption_sync {
    /** @var string Local mirror table. */
    private const TABLE = 'aiprovider_datacurso_consumption';

    /** @var int Records requested per API page. */
    private const PAGE_SIZE = 500;

    /** @var int Safety ceiling of pages processed in a single run (guards against runaway loops). */
    private const MAX_PAGES = 200;

    /**
     * Sync new consumption records from the external API into the local mirror table.
     *
     * Failures are swallowed (logged as debugging) so the report still renders with whatever is
     * already stored locally when the external service is unavailable.
     */
    public static function sync(): void {
        global $DB;

        // Serialise concurrent syncs (e.g. two admins opening the page at once).
        $lockfactory = \core\lock\lock_config::get_lock_factory('aiprovider_datacurso_consumption_sync');
        $lock = $lockfactory->get_lock('sync', 0);
        if (!$lock) {
            return;
        }

        try {
            self::pull($DB);
        } catch (\Throwable $e) {
            debugging('aiprovider_datacurso consumption sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        } finally {
            $lock->release();
        }
    }

    /**
     * Build the API client used to read the consumption history.
     *
     * Declared as a substitution point so the synchronisation can be exercised with a client
     * double, without reaching the network.
     *
     * @return datacurso_api
     */
    protected static function get_api_client(): datacurso_api {
        return new datacurso_api();
    }

    /**
     * Fetch and store every record newer than the last one already stored.
     *
     * @param \moodle_database $db
     */
    private static function pull(\moodle_database $db): void {
        $client = static::get_api_client();
        $licence = static::get_licence_fingerprint();
        $lastid = (int) $db->get_field_sql(
            'SELECT MAX(externalid) FROM {' . self::TABLE . '} WHERE licence = :licence',
            ['licence' => $licence]
        );
        // Records without a user belong to the licence, so to the tenant that holds it.
        $tenants = [0 => tenant_resolver::get_tenant_id()];

        $newrecords = [];
        $seen = [];
        $reachedknown = false;

        for ($page = 1; $page <= self::MAX_PAGES && !$reachedknown; $page++) {
            $response = $client->get('/tokens/historial-consumos', [
                'page' => $page,
                'limit' => self::PAGE_SIZE,
                'shor' => 'id_consumo',
                'shor_dir' => 'desc',
            ]);

            if (empty($response) || ($response['status'] ?? '') !== 'success') {
                break;
            }

            $items = self::extract_items($response);
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $externalid = (int) ($item['id_consumo'] ?? 0);
                if ($externalid <= 0) {
                    continue;
                }
                if ($externalid <= $lastid) {
                    $reachedknown = true;
                    break;
                }
                if (isset($seen[$externalid])) {
                    continue;
                }
                $seen[$externalid] = true;
                $record = self::map_record($item);
                $record->licence = $licence;
                $owner = max(0, $record->userid);
                $tenants[$owner] ??= tenant_resolver::get_tenant_id($owner);
                $record->tenant_id = $tenants[$owner];
                $newrecords[] = $record;
            }

            $totalpages = (int) ($response['paginacion']['total_paginas'] ?? 1);
            if ($page >= $totalpages) {
                break;
            }
        }

        // The external id is unique across the mirror: a record already stored under another
        // licence is not inserted again, which would make the whole insert fail.
        // A full backfill can bring thousands of ids, so they are checked in chunks.
        $stored = [];
        foreach (array_chunk(array_keys($seen), 1000) as $chunk) {
            [$insql, $params] = $db->get_in_or_equal($chunk);
            foreach ($db->get_fieldset_select(self::TABLE, 'externalid', "externalid {$insql}", $params) as $id) {
                $stored[(int) $id] = true;
            }
        }
        $newrecords = array_filter($newrecords, static fn($record) => !isset($stored[$record->externalid]));

        if (!empty($newrecords)) {
            $db->insert_records(self::TABLE, $newrecords);
        }
    }

    /**
     * Fingerprint of the licence the current user's requests are made with.
     *
     * The licence of the tenant of the current user, or the one of the site when the tenant has
     * none: the same one the API client authenticates with. The history is synced and shown for
     * this licence only.
     *
     * @return string Empty when no licence is configured.
     */
    public static function current_licence_fingerprint(): string {
        $licence = trim((string) tenant_config::get('aiprovider_datacurso', tenant_resolver::get_tenant_id(), 'licensekey', ''));
        return $licence === '' ? '' : sha1($licence);
    }

    /**
     * Fingerprint of the licence the history is requested with.
     *
     * @return string
     */
    protected static function get_licence_fingerprint(): string {
        return static::current_licence_fingerprint();
    }

    /**
     * Map a raw external consumption item to a local record.
     *
     * @param array $item
     * @return \stdClass
     */
    private static function map_record(array $item): \stdClass {
        $record = new \stdClass();
        $record->externalid = (int) ($item['id_consumo'] ?? 0);
        $record->userid = (int) ($item['userid'] ?? ($item['id_usuario'] ?? 0));
        $record->service = (string) ($item['id_servicio'] ?? '');
        $record->action = (string) ($item['accion'] ?? '');
        $record->credits = (float) ($item['cantidad_tokens'] ?? 0);
        $record->balance = (float) ($item['saldo_restante'] ?? 0);
        $record->timecreated = self::to_timestamp($item['created_at'] ?? ($item['fecha'] ?? ''));
        return $record;
    }

    /**
     * Parse an external date/datetime string into a Unix timestamp.
     *
     * @param mixed $value
     * @return int
     */
    private static function to_timestamp($value): int {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $timestamp = strtotime($value);
        return $timestamp !== false ? $timestamp : 0;
    }

    /**
     * Flatten the raw consumption items (across all users) from a historial-consumos response.
     *
     * @param array $response
     * @return array<int, array>
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
