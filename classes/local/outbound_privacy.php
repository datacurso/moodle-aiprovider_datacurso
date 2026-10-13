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
 * The single shared source of the user pseudonym sent to the Datacurso services.
 *
 * The services need a stable per-person key so they can keep per-user accounting and enforce
 * the configured rate limits, but they do not need the Moodle user id. This class derives a
 * pseudonym for it with a secret the plugin generates and keeps for the site, for the provider
 * itself and for its consumer plugins, and resolves such pseudonyms back to local users for the
 * consumption history the shop returns. Anything field-specific — masking names in prompts,
 * allowlisting payload keys — is owned by each plugin, which knows its own payload.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class outbound_privacy {
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
     * The secret that keys the user pseudonyms of this site.
     *
     * Frozen contract: the secret is generated once per site (32 random bytes, stored as 64 hex
     * characters in the plugin configuration) at install or upgrade, lazily on first use only as
     * a fallback, and must never be regenerated or transmitted. The remote services keep every per-user counter and rate-limit window under
     * the tokens it produces, and the reverse lookup of the consumption history depends on it.
     * It is deliberately NOT the site UUID sent to the services as `site_id`: a key known to the
     * recipient would make the tokens reversible by enumerating user ids.
     *
     * @return string 64-character lowercase hexadecimal secret.
     */
    public static function get_pseudonym_secret(): string {
        $secret = get_config('aiprovider_datacurso', 'pseudonym_secret');
        if (!empty($secret)) {
            return (string) $secret;
        }

        $secret = bin2hex(random_bytes(32));
        set_config('pseudonym_secret', $secret, 'aiprovider_datacurso');
        return $secret;
    }

    /**
     * Derive a stable, non-reversible, site-scoped token for a user id.
     *
     * The token is an HMAC-SHA256 of the user id keyed with the site's own secret (see
     * {@see get_pseudonym_secret()}), truncated to 32 hex characters. The same user on the same
     * site always yields the same token, the raw Moodle id cannot be recovered from it, and
     * tokens cannot be correlated across sites. It is never empty, so the remote rate limiter
     * always receives a key to enforce against.
     *
     * @param int|string $userid Moodle user id.
     * @return string 32-character lowercase hexadecimal token.
     */
    public static function pseudonymise_userid(int|string $userid): string {
        return substr(hash_hmac('sha256', (string) $userid, self::get_pseudonym_secret()), 0, 32);
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
}
