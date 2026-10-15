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
 * Tests for the outbound privacy helper (user pseudonymisation and its reverse resolution).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\local\outbound_privacy
 */
final class outbound_privacy_test extends \advanced_testcase {
    /**
     * The reverse map and the unresolved set live for the PHP process: start every test clean.
     */
    protected function setUp(): void {
        parent::setUp();
        outbound_privacy::reset_static_caches();
    }

    /**
     * Leave no per-process state behind for the next test class.
     */
    protected function tearDown(): void {
        outbound_privacy::reset_static_caches();
        parent::tearDown();
    }

    /**
     * The same user id on the same site always yields the same token.
     */
    public function test_pseudonymise_userid_is_deterministic(): void {
        $this->resetAfterTest();

        $this->assertSame(outbound_privacy::pseudonymise_userid(42), outbound_privacy::pseudonymise_userid(42));
        $this->assertSame(outbound_privacy::pseudonymise_userid(42), outbound_privacy::pseudonymise_userid('42'));
    }

    /**
     * Different users yield different tokens, and the token never contains the raw id.
     */
    public function test_pseudonymise_userid_differs_between_users(): void {
        $this->resetAfterTest();

        $this->assertNotSame(outbound_privacy::pseudonymise_userid(42), outbound_privacy::pseudonymise_userid(43));
    }

    /**
     * The token is 32 lowercase hexadecimal characters and matches the documented formula.
     */
    public function test_pseudonymise_userid_is_32_lowercase_hex(): void {
        $this->resetAfterTest();

        $token = outbound_privacy::pseudonymise_userid(7);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        $expected = substr(hash_hmac('sha256', '7', outbound_privacy::get_pseudonym_secret()), 0, 32);
        $this->assertSame($expected, $token);
    }

    /**
     * The token is never empty, even for a zero or empty id, so the remote rate limiter
     * always receives a key to enforce against.
     */
    public function test_pseudonymise_userid_is_never_empty(): void {
        $this->resetAfterTest();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', outbound_privacy::pseudonymise_userid(0));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', outbound_privacy::pseudonymise_userid(''));
    }

    /**
     * Only 32 lowercase hexadecimal characters are recognised as a pseudonym.
     */
    public function test_is_pseudonym(): void {
        $this->resetAfterTest();

        $this->assertTrue(outbound_privacy::is_pseudonym(outbound_privacy::pseudonymise_userid(5)));
        $this->assertTrue(outbound_privacy::is_pseudonym(str_repeat('a', 32)));
        $this->assertFalse(outbound_privacy::is_pseudonym(''));
        $this->assertFalse(outbound_privacy::is_pseudonym('42'));
        $this->assertFalse(outbound_privacy::is_pseudonym(str_repeat('a', 31)));
        $this->assertFalse(outbound_privacy::is_pseudonym(str_repeat('a', 33)));
        $this->assertFalse(outbound_privacy::is_pseudonym(strtoupper(str_repeat('a', 32))));
        $this->assertFalse(outbound_privacy::is_pseudonym(str_repeat('g', 32)));
    }

    /**
     * A value that is already a pseudonym is kept as is; anything else is pseudonymised.
     */
    public function test_pseudonymise_userid_value_is_idempotent(): void {
        $this->resetAfterTest();

        $token = outbound_privacy::pseudonymise_userid(9);

        $this->assertSame($token, outbound_privacy::pseudonymise_userid_value(9));
        $this->assertSame($token, outbound_privacy::pseudonymise_userid_value('9'));
        $this->assertSame($token, outbound_privacy::pseudonymise_userid_value($token));
        $this->assertNotSame($token, outbound_privacy::pseudonymise_userid_value(10));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', outbound_privacy::pseudonymise_userid_value(null));
    }

    /**
     * A pseudonym of an existing user resolves back to that user's id; an unknown token is null.
     */
    public function test_resolve_pseudonym_round_trip(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->assertSame((int) $user1->id, outbound_privacy::resolve_pseudonym(outbound_privacy::pseudonymise_userid($user1->id)));
        $this->assertSame((int) $user2->id, outbound_privacy::resolve_pseudonym(outbound_privacy::pseudonymise_userid($user2->id)));
        $this->assertNull(outbound_privacy::resolve_pseudonym(str_repeat('0', 32)));
    }

    /**
     * After a rebuild every known token is in the cache, and a user created later is found
     * through a fresh rebuild on miss in a new process (static caches reset).
     */
    public function test_resolve_pseudonym_uses_cache_and_rebuilds_on_miss(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $token1 = outbound_privacy::pseudonymise_userid($user1->id);

        $cache = \cache::make('aiprovider_datacurso', 'pseudonyms');
        $this->assertFalse($cache->get($token1));

        $this->assertSame((int) $user1->id, outbound_privacy::resolve_pseudonym($token1));
        $this->assertSame((int) $user1->id, (int) $cache->get($token1));

        // A user created after the rebuild is a cache miss, which triggers a new rebuild in the
        // next process (the map is built at most once per process).
        $user2 = $this->getDataGenerator()->create_user();
        $token2 = outbound_privacy::pseudonymise_userid($user2->id);
        $this->assertFalse($cache->get($token2));
        outbound_privacy::reset_static_caches();
        $this->assertSame((int) $user2->id, outbound_privacy::resolve_pseudonym($token2));
        $this->assertSame((int) $user2->id, (int) $cache->get($token2));
    }

    /**
     * Deleted users are not part of the reverse map.
     */
    public function test_resolve_pseudonym_ignores_deleted_users(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        delete_user($user);

        $this->assertNull(outbound_privacy::resolve_pseudonym(outbound_privacy::pseudonymise_userid($user->id)));
    }

    /**
     * Unresolvable tokens cost one rebuild per process, not one per token: the second unknown
     * token performs no database read, and a known token still resolves afterwards.
     *
     * RES-001: bounded cost of the reverse lookup during a sync.
     */
    public function test_unknown_tokens_rebuild_the_map_at_most_once_per_process(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $known = outbound_privacy::pseudonymise_userid($user->id);

        $before = $DB->perf_get_reads();
        $this->assertNull(outbound_privacy::resolve_pseudonym(str_repeat('0', 32)));
        $afterfirst = $DB->perf_get_reads();
        $this->assertGreaterThan($before, $afterfirst, 'The first miss rebuilds the map from the database.');

        $this->assertNull(outbound_privacy::resolve_pseudonym(str_repeat('1', 32)));
        $this->assertNull(outbound_privacy::resolve_pseudonym(str_repeat('0', 32)));
        $this->assertSame($afterfirst, $DB->perf_get_reads(), 'Later misses must not read the database again.');

        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym($known));
        $this->assertSame($afterfirst, $DB->perf_get_reads());
    }

    /**
     * A user created after the per-process map was built is found once the static caches are
     * reset (a new request).
     */
    public function test_reset_static_caches_allows_a_later_user_to_resolve(): void {
        $this->resetAfterTest();

        $this->assertNull(outbound_privacy::resolve_pseudonym(str_repeat('0', 32)));

        $user = $this->getDataGenerator()->create_user();
        $token = outbound_privacy::pseudonymise_userid($user->id);
        $this->assertNull(outbound_privacy::resolve_pseudonym($token), 'Within the process the map is not rebuilt again.');

        outbound_privacy::reset_static_caches();
        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym($token));
    }

    /**
     * Deleting a user drops its token from the reverse map, so a cached token no longer
     * attributes consumption to a deleted account.
     *
     * REL-003: the cache follows user deletion.
     */
    public function test_deleted_user_token_no_longer_resolves(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $token = outbound_privacy::pseudonymise_userid($user->id);
        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym($token));
        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym($token), 'Served from the cache.');

        delete_user($user);

        $this->assertNull(outbound_privacy::resolve_pseudonym($token));
        $this->assertFalse(\cache::make('aiprovider_datacurso', 'pseudonyms')->get($token));
    }

    /**
     * The HMAC secret is generated once per site (64 lowercase hex characters), persisted in the
     * plugin configuration and returned unchanged afterwards.
     */
    public function test_pseudonym_secret_is_generated_once_and_persisted(): void {
        $this->resetAfterTest();

        // The install already generated one: start from a site without it.
        unset_config('pseudonym_secret', 'aiprovider_datacurso');
        $this->assertEmpty(get_config('aiprovider_datacurso', 'pseudonym_secret'));

        $secret = outbound_privacy::get_pseudonym_secret();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
        $this->assertSame($secret, get_config('aiprovider_datacurso', 'pseudonym_secret'));
        $this->assertSame($secret, outbound_privacy::get_pseudonym_secret());
    }

    /**
     * The secret is the plugin's own, not the site UUID sent to the services as site_id.
     */
    public function test_pseudonym_secret_is_not_the_site_uuid(): void {
        $this->resetAfterTest();

        $this->assertNotSame(
            \aiprovider_datacurso\httpclient\datacurso_api_base::get_site_uuid(),
            outbound_privacy::get_pseudonym_secret()
        );
    }

    /**
     * Tokens are scoped by the secret: another secret yields other tokens, and a token resolved
     * under the previous secret no longer resolves once the caches are cleared.
     */
    public function test_tokens_are_scoped_by_the_secret(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $token = outbound_privacy::pseudonymise_userid($user->id);
        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym($token));

        set_config('pseudonym_secret', str_repeat('ab', 32), 'aiprovider_datacurso');
        outbound_privacy::reset_static_caches();
        \cache::make('aiprovider_datacurso', 'pseudonyms')->purge();

        $this->assertNotSame($token, outbound_privacy::pseudonymise_userid($user->id));
        $this->assertNull(outbound_privacy::resolve_pseudonym($token));
        $this->assertSame((int) $user->id, outbound_privacy::resolve_pseudonym(outbound_privacy::pseudonymise_userid($user->id)));
    }

    /**
     * A fresh install generates the secret, so no request ever has to write it lazily.
     */
    public function test_install_generates_the_secret(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/ai/provider/datacurso/db/install.php');

        unset_config('pseudonym_secret', 'aiprovider_datacurso');
        xmldb_aiprovider_datacurso_install();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) get_config('aiprovider_datacurso', 'pseudonym_secret'));
    }

    /**
     * A site upgraded from an earlier release gets its secret from the upgrade step.
     */
    public function test_upgrade_generates_the_secret(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/ai/provider/datacurso/db/upgrade.php');

        unset_config('pseudonym_secret', 'aiprovider_datacurso');
        set_config('version', 2026100500, 'aiprovider_datacurso');
        xmldb_aiprovider_datacurso_upgrade(2026100500);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) get_config('aiprovider_datacurso', 'pseudonym_secret'));
        $this->assertSame('2026100900', get_config('aiprovider_datacurso', 'version'));
    }
}
