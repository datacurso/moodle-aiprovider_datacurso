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

namespace aiprovider_datacurso\local;

use aiprovider_datacurso\httpclient\datacurso_api;
use core\exception\moodle_exception;

/**
 * Manager for CRUD operations over aiprovider_datacurso_userlimit.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_token_limit_manager {
    /**
     * Get current license pool information for user-limit assignments.
     *
     * @param int|null $excludingid Optional user-limit record id to exclude from assigned total.
     * @return array{status:string,licensebalance:int,assignedtotal:int,availabletoassign:int}
     */
    public static function get_license_pool(?int $excludingid = null): array {
        global $DB;

        $sql = 'SELECT COALESCE(SUM(tokenlimit), 0) FROM {aiprovider_datacurso_userlimit}';
        $params = [];
        if (!empty($excludingid)) {
            $sql .= ' WHERE id <> :excludingid';
            $params['excludingid'] = $excludingid;
        }

        $assignedtotal = (int)$DB->get_field_sql($sql, $params);

        try {
            $client = new datacurso_api();
            $response = $client->get('/tokens/saldo');
            if (empty($response) || ($response['status'] ?? 'error') !== 'success') {
                return [
                    'status' => 'error',
                    'licensebalance' => 0,
                    'assignedtotal' => $assignedtotal,
                    'availabletoassign' => 0,
                ];
            }

            $licensebalance = (int)($response['saldo_actual'] ?? 0);
            $availabletoassign = max(0, $licensebalance - $assignedtotal);

            return [
                'status' => 'success',
                'licensebalance' => $licensebalance,
                'assignedtotal' => $assignedtotal,
                'availabletoassign' => $availabletoassign,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'licensebalance' => 0,
                'assignedtotal' => $assignedtotal,
                'availabletoassign' => 0,
            ];
        }
    }

    /**
     * Count records matching optional search.
     *
     * @param string $search
     * @return int
     */
    public static function count(string $search = ''): int {
        global $DB;
        [$where, $params] = self::build_search_where($search);
        $sql = "SELECT COUNT(1)
                  FROM {aiprovider_datacurso_userlimit} utl
                  JOIN {user} u ON u.id = utl.userid
                 $where";
        return (int)$DB->get_field_sql($sql, $params);
    }

    /**
     * Reset usage counters for a user limit record.
     *
     * @param int $id Record ID
     * @return bool
     */
    public static function reset_usage(int $id): bool {
        global $DB, $USER;
        if (!$DB->record_exists('aiprovider_datacurso_userlimit', ['id' => $id])) {
            return false;
        }
        $now = time();
        $record = (object) [
            'id' => $id,
            'tokensused' => 0,
            'countfrom' => $now,
            'usermodified' => $USER->id,
            'timemodified' => $now,
        ];
        $DB->update_record('aiprovider_datacurso_userlimit', $record);
        return true;
    }

    /**
     * Process due recurring quota resets.
     *
     * When there are enough license credits for a record's configured limit,
     * its usage window is reset. If there are not enough credits, the record is
     * skipped for this cycle and only the next reset date is moved forward.
     *
     * @param int|null $now Optional timestamp used for testing.
     * @return int Number of records successfully reset.
     */
    public static function process_recurring_resets(?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $records = $DB->get_records_select(
            'aiprovider_datacurso_userlimit',
            'nextresetat > 0 AND nextresetat <= :now AND recurringintervalenabled = 1 AND recurringintervalvalue > 0',
            ['now' => $now],
            'nextresetat ASC, id ASC'
        );

        $resetcount = 0;
        foreach ($records as $record) {
            [$enabled, $intervalunit, $intervalvalue] = self::extract_interval_config($record);
            if (!$enabled) {
                continue;
            }

            $canreset = false;
            $pool = self::get_license_pool((int)$record->id);
            if (($pool['status'] ?? 'error') === 'success') {
                $canreset = (int)$record->tokenlimit <= (int)$pool['availabletoassign'];
            }

            if ($canreset) {
                $record->tokensused = 0;
                $record->countfrom = $now;
                $record->lastsync = $now;
                $resetcount++;
            }

            $record->nextresetat = self::calculate_next_reset_at(
                (int)$record->nextresetat,
                $enabled,
                $intervalunit,
                $intervalvalue,
                $now
            );
            $record->timemodified = $now;
            $DB->update_record('aiprovider_datacurso_userlimit', $record);
        }

        return $resetcount;
    }

    /**
     * Get paginated records with user data.
     *
     * @param string $search
     * @param string $sort allowed: fullname|email|tokenlimit|tokensused
     * @param string $dir ASC|DESC
     * @param int $offset
     * @param int $limit
     * @return array of records (stdClass)
     */
    public static function get_records(string $search, string $sort, string $dir, int $offset, int $limit): array {
        global $DB;
        [$where, $params] = self::build_search_where($search);

        $orderby = self::map_sort($sort, $dir);
        $sql = "SELECT utl.id, utl.userid, utl.tokenlimit, utl.tokensused,
                       u.firstname, u.lastname, u.email
                  FROM {aiprovider_datacurso_userlimit} utl
                  JOIN {user} u ON u.id = utl.userid
                 $where
              ORDER BY $orderby";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get a single record by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('aiprovider_datacurso_userlimit', ['id' => $id]) ?: null;
    }

    /**
     * Delete a record by id.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;
        return $DB->delete_records('aiprovider_datacurso_userlimit', ['id' => $id]);
    }

    /**
     * Create or update a user quota record.
     *
     * @param int $userid
     * @param int $tokenlimit
     * @param int|null $id existing id to update
     * @param int $recurringintervalenabled
     * @param string $recurringintervalunit
     * @param int $recurringintervalvalue
     * @return int record id
     */
    public static function save(
        int $userid,
        int $tokenlimit,
        ?int $id = null,
        int $recurringintervalenabled = 0,
        string $recurringintervalunit = 'day',
        int $recurringintervalvalue = 0
    ): int {
        global $DB, $USER;
        $now = time();
        $recurringintervalenabled = !empty($recurringintervalenabled) ? 1 : 0;
        $recurringintervalunit = self::normalize_interval_unit($recurringintervalunit);
        $recurringintervalvalue = max(0, $recurringintervalvalue);

        if (!$recurringintervalenabled) {
            $recurringintervalvalue = 0;
        }

        $nextresetat = self::calculate_next_reset_at(
            0,
            (bool)$recurringintervalenabled,
            $recurringintervalunit,
            $recurringintervalvalue,
            $now
        );

        $pool = self::get_license_pool($id ?: null);
        if (($pool['status'] ?? 'error') !== 'success') {
            throw new moodle_exception('errorgetbalancecredits', 'aiprovider_datacurso');
        }

        $availabletoassign = (int)($pool['availabletoassign'] ?? 0);
        if ($tokenlimit > $availabletoassign) {
            $params = (object)[
                'requested' => $tokenlimit,
                'available' => $availabletoassign,
            ];
            throw new moodle_exception('error_usertokenlimit_available_exceeded', 'aiprovider_datacurso', '', $params);
        }

        if ($id) {
            $record = $DB->get_record('aiprovider_datacurso_userlimit', ['id' => $id], '*', MUST_EXIST);
            $record->tokenlimit = $tokenlimit;
            $record->recurringintervalenabled = $recurringintervalenabled;
            $record->recurringintervalunit = $recurringintervalunit;
            $record->recurringintervalvalue = $recurringintervalvalue;
            $record->nextresetat = $nextresetat;
            $record->usermodified = $USER->id;
            $record->timemodified = $now;
            $DB->update_record('aiprovider_datacurso_userlimit', $record);
            return $record->id;
        }

        // Check if a record already exists for this user.
        if ($DB->record_exists('aiprovider_datacurso_userlimit', ['userid' => $userid])) {
            throw new moodle_exception('error_usertokenlimit_exists', 'aiprovider_datacurso');
        }

        $record = (object) [
            'userid' => $userid,
            'tokenlimit' => $tokenlimit,
            'tokensused' => 0,
            'countfrom' => $now,
            'lastsync' => 0,
            'recurringintervalenabled' => $recurringintervalenabled,
            'recurringintervalunit' => $recurringintervalunit,
            'recurringintervalvalue' => $recurringintervalvalue,
            'nextresetat' => $nextresetat,
            'usermodified' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int)$DB->insert_record('aiprovider_datacurso_userlimit', $record);
    }

    /**
     * Calculate the next reset timestamp for recurring assignment.
     *
     * @param int $currentnextreset Current stored reset timestamp.
     * @param bool $enabled
     * @param string $intervalunit Recurrence unit.
     * @param int $intervalvalue Recurrence value.
     * @param int $now Current timestamp.
     * @return int
     */
    private static function calculate_next_reset_at(
        int $currentnextreset,
        bool $enabled,
        string $intervalunit,
        int $intervalvalue,
        int $now
    ): int {
        if (!$enabled || $intervalvalue <= 0) {
            return 0;
        }

        $nextresetat = $currentnextreset > 0
            ? $currentnextreset
            : self::add_interval_to_timestamp($now, $intervalunit, $intervalvalue);
        if ($nextresetat <= 0) {
            return 0;
        }

        while ($nextresetat <= $now) {
            $nextresetat = self::add_interval_to_timestamp($nextresetat, $intervalunit, $intervalvalue);
            if ($nextresetat <= 0) {
                return 0;
            }
        }

        return $nextresetat;
    }

    /**
     * Normalize supported interval units.
     *
     * @param string $unit
     * @return string
     */
    private static function normalize_interval_unit(string $unit): string {
        $unit = trim(\core_text::strtolower($unit));
        $supported = ['hour', 'day', 'week', 'month', 'year'];
        return in_array($unit, $supported, true) ? $unit : 'day';
    }

    /**
     * Add interval to timestamp using DateTime arithmetic.
     *
     * @param int $timestamp
     * @param string $unit
     * @param int $value
     * @return int
     */
    private static function add_interval_to_timestamp(int $timestamp, string $unit, int $value): int {
        if ($timestamp <= 0 || $value <= 0) {
            return 0;
        }

        $dt = new \DateTimeImmutable('@' . $timestamp);
        $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
        $modified = $dt->modify('+' . $value . ' ' . $unit);
        if ($modified === false) {
            return 0;
        }

        return $modified->getTimestamp();
    }

    /**
     * Get effective recurrence configuration from record.
     *
     * @param \stdClass $record
     * @return array{0:bool,1:string,2:int}
     */
    private static function extract_interval_config(\stdClass $record): array {
        $enabled = !empty($record->recurringintervalenabled);
        $unit = self::normalize_interval_unit((string)($record->recurringintervalunit ?? 'day'));
        $value = (int)($record->recurringintervalvalue ?? 0);
        return [($enabled && $value > 0), $unit, $value];
    }

    /**
     * Build search SQL for name or email.
     *
     * @param string $search
     * @return array [where, params]
     */
    private static function build_search_where(string $search): array {
        global $DB;
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE (' .
                $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) .
            ')';
            $like = "%$search%";
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }
        return [$where, $params];
    }

    /**
     * Map UI sort to SQL order by.
     *
     * @param string $sort
     * @param string $dir
     * @return string
     */
    private static function map_sort(string $sort, string $dir): string {
        $dir = (strtoupper($dir) === 'DESC') ? 'DESC' : 'ASC';
        return match ($sort) {
            'fullname' => "u.lastname $dir, u.firstname $dir",
            'email' => "u.email $dir",
            'tokenlimit' => "utl.tokenlimit $dir",
            'tokensused' => "utl.tokensused $dir",
            'tokensavailable' => "(utl.tokenlimit - utl.tokensused) $dir",
            default => "u.email $dir",
        };
    }
}
