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
 * Tests for the outbound privacy helper (user pseudonymisation and payload masking).
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
        $expected = substr(hash_hmac('sha256', '7', get_site_identifier() . outbound_privacy::PSEUDONYM_KEY_SUFFIX), 0, 32);
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
     * Placeholders are built for the full name, first name and last name, skipping empty values.
     */
    public function test_placeholders_for_user(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Pérez']);
        $this->assertSame([
            '[STUDENT_NAME]' => fullname($user),
            '[STUDENT_FIRSTNAME]' => 'Ana',
            '[STUDENT_LASTNAME]' => 'Pérez',
        ], outbound_privacy::placeholders_for_user($user));

        $nolastname = clone $user;
        $nolastname->lastname = '';
        $placeholders = outbound_privacy::placeholders_for_user($nolastname);
        $this->assertArrayHasKey('[STUDENT_NAME]', $placeholders);
        $this->assertArrayHasKey('[STUDENT_FIRSTNAME]', $placeholders);
        $this->assertArrayNotHasKey('[STUDENT_LASTNAME]', $placeholders);

        $noname = clone $user;
        $noname->firstname = '';
        $noname->lastname = '';
        $this->assertSame([], outbound_privacy::placeholders_for_user($noname));
    }

    /**
     * Masking replaces the longest names first, so the full name is not split into its parts,
     * and restoring puts the names back.
     */
    public function test_mask_and_restore_text(): void {
        $this->resetAfterTest();

        $replacements = [
            '[STUDENT_FIRSTNAME]' => 'Ana',
            '[STUDENT_NAME]' => 'Ana Pérez',
            '[STUDENT_LASTNAME]' => 'Pérez',
        ];
        $text = 'Dear Ana Pérez, Ana did well. Regards to the Pérez family.';

        $masked = outbound_privacy::mask_text($text, $replacements);
        $this->assertSame(
            'Dear [STUDENT_NAME], [STUDENT_FIRSTNAME] did well. Regards to the [STUDENT_LASTNAME] family.',
            $masked
        );
        $this->assertSame($text, outbound_privacy::restore_text($masked, $replacements));

        $this->assertSame('', outbound_privacy::mask_text('', $replacements));
        $this->assertSame('plain', outbound_privacy::mask_text('plain', []));
        $this->assertSame('plain', outbound_privacy::restore_text('plain', []));
    }

    /**
     * Only allowlisted keys survive.
     */
    public function test_apply_allowlist(): void {
        $this->resetAfterTest();

        $payload = ['prompt' => 'p', 'email' => 'a@b.c', 'userid' => 3];

        $this->assertSame(['prompt' => 'p', 'userid' => 3], outbound_privacy::apply_allowlist($payload, ['prompt', 'userid']));
        $this->assertSame([], outbound_privacy::apply_allowlist($payload, []));
    }

    /**
     * End to end: allowlist, pseudonymous userid, student name placeholder and masked fields.
     */
    public function test_anonymise_payload_end_to_end(): void {
        $this->resetAfterTest();

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Pérez']);
        $payload = [
            'userid' => 42,
            'student_name' => 'Ana Pérez',
            'submission' => 'Ana Pérez wrote this. Ana signed it.',
            'prompt' => 'Grade the work of Pérez',
            'email' => 'ana@example.com',
        ];

        $result = outbound_privacy::anonymise_payload(
            $payload,
            ['userid', 'student_name', 'submission', 'prompt'],
            $student,
            ['submission']
        );

        $this->assertSame(outbound_privacy::pseudonymise_userid(42), $result['payload']['userid']);
        $this->assertSame('[STUDENT_NAME]', $result['payload']['student_name']);
        $this->assertSame('[STUDENT_NAME] wrote this. [STUDENT_FIRSTNAME] signed it.', $result['payload']['submission']);
        // Not listed in the mask fields: left untouched.
        $this->assertSame('Grade the work of Pérez', $result['payload']['prompt']);
        $this->assertArrayNotHasKey('email', $result['payload']);
        $this->assertSame(outbound_privacy::placeholders_for_user($student), $result['replacements']);

        // The reply can be restored with the returned replacements.
        $this->assertSame(
            'Well done Ana Pérez',
            outbound_privacy::restore_text('Well done [STUDENT_NAME]', $result['replacements'])
        );
    }

    /**
     * Without allowlist or student, only the userid is pseudonymised and nothing else changes.
     */
    public function test_anonymise_payload_without_options(): void {
        $this->resetAfterTest();

        $token = outbound_privacy::pseudonymise_userid(1);
        $result = outbound_privacy::anonymise_payload(['userid' => $token, 'text' => 'Ana']);

        $this->assertSame(['userid' => $token, 'text' => 'Ana'], $result['payload']);
        $this->assertSame([], $result['replacements']);

        $result = outbound_privacy::anonymise_payload(['text' => 'Ana']);
        $this->assertSame(['text' => 'Ana'], $result['payload']);
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
}
