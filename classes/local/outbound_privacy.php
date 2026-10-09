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

/**
 * Outbound privacy helper: what leaves the site towards the Datacurso services.
 *
 * The services need a stable per-person key so they can keep per-user accounting and enforce
 * the configured rate limits, but they do not need the Moodle user id. This class derives a
 * site-scoped pseudonym for it, resolves such pseudonyms back to local users (for the
 * consumption history that the shop returns) and offers the masking primitives the consumer
 * plugins use to keep student names out of the prompts.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class outbound_privacy {
    /** @var string Placeholder that replaces the student full name. */
    public const PLACEHOLDER_NAME = '[STUDENT_NAME]';

    /** @var string Placeholder that replaces the student first name. */
    public const PLACEHOLDER_FIRSTNAME = '[STUDENT_FIRSTNAME]';

    /** @var string Placeholder that replaces the student last name. */
    public const PLACEHOLDER_LASTNAME = '[STUDENT_LASTNAME]';

    /**
     * Suffix appended to the site identifier to form the HMAC key of the user pseudonyms.
     *
     * Frozen contract: the remote services keep per-user accounting and rate-limit windows under
     * the resulting tokens, and the consumer plugins derive the same tokens on their side. Changing
     * it would silently reset every remote per-user counter and break the reverse lookup of the
     * consumption history, so it must never change.
     *
     * @var string
     */
    public const PSEUDONYM_KEY_SUFFIX = '|aiprovider_datacurso';

    /** @var string Cache area holding the pseudonym to user id map. */
    private const CACHE_AREA = 'pseudonyms';

    /**
     * Per-process reverse map (token => user id), built at most once per PHP process.
     *
     * A sync may meet many tokens unknown to the cache (history rows of deleted users, rows
     * written by another site of the same licence); rebuilding the map for each of them would
     * cost one pass over the user table per token. Null until the first miss builds it.
     *
     * @var array<string, int>|null
     */
    private static ?array $map = null;

    /**
     * Tokens already found unresolvable in this process, so repeated ones cost nothing.
     *
     * @var array<string, true>
     */
    private static array $unresolved = [];

    /**
     * Derive a stable, non-reversible, site-scoped token for a user id.
     *
     * The token is an HMAC-SHA256 of the user id keyed with the site identifier, truncated to
     * 32 hex characters. The same user on the same site always yields the same token, the raw
     * Moodle id cannot be recovered from it, and tokens cannot be correlated across sites. It is
     * never empty, so the remote rate limiter always receives a key to enforce against.
     *
     * @param int|string $userid Moodle user id.
     * @return string 32-character lowercase hexadecimal token.
     */
    public static function pseudonymise_userid(int|string $userid): string {
        $key = get_site_identifier() . self::PSEUDONYM_KEY_SUFFIX;

        return substr(hash_hmac('sha256', (string) $userid, $key), 0, 32);
    }

    /**
     * Whether a value has the shape of a pseudonym produced by {@see pseudonymise_userid()}.
     *
     * @param string $value Value to inspect.
     * @return bool
     */
    public static function is_pseudonym(string $value): bool {
        return (bool) preg_match('/^[0-9a-f]{32}$/', $value);
    }

    /**
     * Pseudonymise a userid value coming from a caller, keeping it as is when it already is one.
     *
     * Consumer plugins may hand over either the raw Moodle id or a pseudonym they derived
     * themselves; both end up as the same outbound token.
     *
     * @param mixed $value Raw user id or pseudonym.
     * @return string 32-character lowercase hexadecimal token.
     */
    public static function pseudonymise_userid_value(mixed $value): string {
        if (is_string($value) && self::is_pseudonym($value)) {
            return $value;
        }

        return self::pseudonymise_userid(is_scalar($value) ? (string) $value : '');
    }

    /**
     * Resolve a pseudonym back to the local Moodle user id.
     *
     * The reverse map is kept in an application cache. On a miss the whole map is rebuilt from
     * the non-deleted user ids (ids only, streamed through a recordset so a large site does not
     * load every user object), which also picks up users created since the last rebuild. The
     * rebuild happens at most once per PHP process, and tokens found unresolvable are remembered
     * for the process, so a sync meeting many unknown tokens pays for one pass over the user table.
     *
     * @param string $token Pseudonym as produced by {@see pseudonymise_userid()}.
     * @return int|null The user id, or null when no non-deleted user matches.
     */
    public static function resolve_pseudonym(string $token): ?int {
        if (!self::is_pseudonym($token) || isset(self::$unresolved[$token])) {
            return null;
        }

        $cache = \cache::make('aiprovider_datacurso', self::CACHE_AREA);
        $userid = $cache->get($token);
        if ($userid !== false) {
            return (int) $userid;
        }

        if (self::$map === null) {
            self::$map = self::build_pseudonym_map();
            $cache->set_many(self::$map);
        }

        if (isset(self::$map[$token])) {
            return (int) self::$map[$token];
        }

        self::$unresolved[$token] = true;
        return null;
    }

    /**
     * Forget the per-process reverse map and unresolved set (tests, or a long-running process).
     */
    public static function reset_static_caches(): void {
        self::$map = null;
        self::$unresolved = [];
    }

    /**
     * Drop a deleted user's token from the reverse map, so its consumption is no longer
     * attributed to the deleted account.
     *
     * Observer of {@see \core\event\user_deleted}, declared in db/events.php.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        $token = self::pseudonymise_userid($event->objectid);

        \cache::make('aiprovider_datacurso', self::CACHE_AREA)->delete($token);
        if (self::$map !== null) {
            unset(self::$map[$token]);
        }
        self::$unresolved[$token] = true;
    }

    /**
     * Build the full pseudonym to user id map for the non-deleted users of the site.
     *
     * @return array<string, int>
     */
    private static function build_pseudonym_map(): array {
        global $DB;

        $map = [];
        $rs = $DB->get_recordset('user', ['deleted' => 0], '', 'id');
        foreach ($rs as $record) {
            $map[self::pseudonymise_userid($record->id)] = (int) $record->id;
        }
        $rs->close();

        return $map;
    }

    /**
     * Build the placeholder to name map for a student, skipping empty values.
     *
     * @param \stdClass $user User record (at least the name fields used by fullname()).
     * @return array<string, string> Placeholder => original value.
     */
    public static function placeholders_for_user(\stdClass $user): array {
        $replacements = [];

        $studentname = trim(fullname($user));
        if ($studentname !== '') {
            $replacements[self::PLACEHOLDER_NAME] = $studentname;
        }

        if (!empty($user->firstname) && is_string($user->firstname)) {
            $replacements[self::PLACEHOLDER_FIRSTNAME] = $user->firstname;
        }

        if (!empty($user->lastname) && is_string($user->lastname)) {
            $replacements[self::PLACEHOLDER_LASTNAME] = $user->lastname;
        }

        return $replacements;
    }

    /**
     * Replace names with their placeholders, longest name first so a full name is not split.
     *
     * @param string $text Text to mask.
     * @param array<string, string> $replacements Placeholder => original value.
     * @return string
     */
    public static function mask_text(string $text, array $replacements): string {
        if ($text === '' || empty($replacements)) {
            return $text;
        }

        uasort($replacements, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace(array_values($replacements), array_keys($replacements), $text);
    }

    /**
     * Put the names back in place of their placeholders.
     *
     * @param string $text Text to restore (typically the AI reply).
     * @param array<string, string> $replacements Placeholder => original value.
     * @return string
     */
    public static function restore_text(string $text, array $replacements): string {
        if ($text === '' || empty($replacements)) {
            return $text;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Keep only the allowlisted keys of a payload.
     *
     * @param array $payload Payload to filter.
     * @param string[] $allowedkeys Keys allowed to leave the site.
     * @return array
     */
    public static function apply_allowlist(array $payload, array $allowedkeys): array {
        return array_intersect_key($payload, array_flip($allowedkeys));
    }

    /**
     * Anonymise an outbound payload in one go.
     *
     * Applies the allowlist when given, pseudonymises the userid when present, and when a
     * student is given builds the name placeholders, replaces a `student_name` field by the
     * full-name placeholder and masks the listed string fields.
     *
     * @param array $payload Original payload.
     * @param string[]|null $allowlist Keys allowed to leave the site; null keeps every key.
     * @param \stdClass|null $student Student whose names must not leave the site.
     * @param string[] $maskfields Payload string fields in which the student names are masked.
     * @return array{payload: array, replacements: array<string, string>}
     */
    public static function anonymise_payload(
        array $payload,
        ?array $allowlist = null,
        ?\stdClass $student = null,
        array $maskfields = []
    ): array {
        if ($allowlist !== null) {
            $payload = self::apply_allowlist($payload, $allowlist);
        }

        if (array_key_exists('userid', $payload)) {
            $payload['userid'] = self::pseudonymise_userid_value($payload['userid']);
        }

        $replacements = $student !== null ? self::placeholders_for_user($student) : [];

        if (!empty($replacements)) {
            if (isset($payload['student_name']) && is_string($payload['student_name']) && $payload['student_name'] !== '') {
                $payload['student_name'] = self::PLACEHOLDER_NAME;
            }

            foreach ($maskfields as $field) {
                if (isset($payload[$field]) && is_string($payload[$field])) {
                    $payload[$field] = self::mask_text($payload[$field], $replacements);
                }
            }
        }

        return [
            'payload' => $payload,
            'replacements' => $replacements,
        ];
    }
}
