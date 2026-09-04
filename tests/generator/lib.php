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
 * Component data generator for aiprovider_datacurso.
 *
 * Used by PHPUnit tests directly (via {@see testing_data_generator::get_plugin_generator()})
 * and by the Behat generator {@see behat_aiprovider_datacurso_generator}, which delegates to
 * {@see self::create_consumption()} for the "aiprovider_datacurso > consumption" entity.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aiprovider_datacurso_generator extends component_generator_base {
    /** @var int Counter used to auto-assign a unique externalid when one is not supplied. */
    protected int $externalidcounter = 0;

    /**
     * Create a consumption record in the local mirror table
     * ({aiprovider_datacurso_consumption}).
     *
     * Any field not supplied falls back to a sensible default: externalid auto-increments per
     * generator instance, userid defaults to 0 (and can be given as a username instead of an
     * id), service/action default to an empty string, credits/balance default to 0, and
     * timecreated defaults to the current time.
     *
     * @param array|stdClass $record
     * @return stdClass The inserted record, including its new id.
     */
    public function create_consumption(array|stdClass $record): stdClass {
        global $DB;

        $record = (object) $record;

        if (isset($record->externalid)) {
            $this->externalidcounter = max($this->externalidcounter, (int) $record->externalid);
        } else {
            $record->externalid = ++$this->externalidcounter;
        }

        $record->userid = $this->resolve_userid($record->userid ?? 0);
        $record->service = (string) ($record->service ?? '');
        $record->action = (string) ($record->action ?? '');
        $record->credits = $record->credits ?? 0;
        $record->balance = $record->balance ?? 0;
        $record->timecreated = $this->resolve_timecreated($record->timecreated ?? time());

        $record->id = $DB->insert_record('aiprovider_datacurso_consumption', $record);

        return $record;
    }

    /**
     * Resolve a userid value that may be supplied as a numeric id or as a username.
     *
     * @param int|string $userid
     * @return int
     */
    protected function resolve_userid(int|string $userid): int {
        global $DB;

        if (is_numeric($userid)) {
            return (int) $userid;
        }

        $username = trim($userid);
        if ($username === '') {
            return 0;
        }

        return (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
    }

    /**
     * Resolve a timecreated value that may be an int timestamp or a parseable date string.
     *
     * Accepts Moodle's Behat "##relative date##" convention (e.g. "##yesterday##",
     * "##today##", "##2026-01-15##") by stripping the surrounding hashes before parsing, since
     * that substitution is not performed automatically for generator table data.
     *
     * @param int|string $value
     * @return int
     */
    protected function resolve_timecreated(int|string $value): int {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = trim($value, "# \t\n\r\0\x0B");
        $timestamp = strtotime($value);

        return $timestamp !== false ? $timestamp : time();
    }
}
