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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/testable_license_region.php');

/**
 * The region of a licence is resolved once and kept.
 *
 * See MDL-INT-039 of cases_data/dttutor/dttutor-2.0.10.md. The region decides which host serves
 * the site and is a property of the licence, so asking the shop for it on every request made each
 * question cost a round trip and tied the service to a shop it does not need.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\license_region
 */
final class license_region_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        testable_license_region::reset();
        set_config('licensekey', 'DC-A-LICENCE', 'aiprovider_datacurso');
    }

    /**
     * Make the answer held in the configuration old enough to be asked for again.
     */
    private function let_the_answer_go_stale(): void {
        $stale = time() - license_region::TTL - 1;
        set_config(license_region::CHECKED, $stale, 'aiprovider_datacurso');
    }

    /**
     * MDL-INT-039: the shop is asked once, however many requests follow.
     */
    public function test_the_region_is_asked_for_once_and_kept(): void {
        testable_license_region::$european = true;

        $first = testable_license_region::is_european();
        testable_license_region::is_european();
        $third = testable_license_region::is_european();

        $this->assertTrue($first);
        $this->assertTrue($third);
        $this->assertSame(1, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: the standard region is kept the same way as the European one.
     */
    public function test_the_standard_region_is_kept_too(): void {
        testable_license_region::$european = false;

        $this->assertFalse(testable_license_region::is_european());
        $this->assertFalse(testable_license_region::is_european());
        $this->assertSame(1, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: a shop that does not answer no longer takes the service with it.
     */
    public function test_a_shop_that_is_down_does_not_stop_a_known_licence(): void {
        testable_license_region::$european = true;
        testable_license_region::is_european();
        $this->let_the_answer_go_stale();
        testable_license_region::$failure = new \moodle_exception('error');

        $answer = testable_license_region::is_european();

        $this->assertTrue($answer);
        $this->assertDebuggingCalled();
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: a licence whose region nobody knows is not guessed at.
     *
     * Guessing would send the data of a site to a region it may not be allowed to reach, which is
     * the one thing this resolution exists to decide.
     */
    public function test_an_unknown_region_is_never_guessed(): void {
        testable_license_region::$failure = new \moodle_exception('error');

        $this->expectException(\moodle_exception::class);
        testable_license_region::is_european();
    }

    /**
     * MDL-INT-039: a new licence resolves its own region.
     */
    public function test_changing_the_licence_asks_again(): void {
        testable_license_region::$european = false;
        $this->assertFalse(testable_license_region::is_european());
        set_config('licensekey', 'DC-ANOTHER-LICENCE', 'aiprovider_datacurso');
        testable_license_region::$european = true;

        $this->assertTrue(testable_license_region::is_european());
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: a new licence is never answered for by the previous one.
     */
    public function test_a_new_licence_is_not_answered_for_by_the_previous_one(): void {
        testable_license_region::$european = true;
        testable_license_region::is_european();
        set_config('licensekey', 'DC-ANOTHER-LICENCE', 'aiprovider_datacurso');
        testable_license_region::$failure = new \moodle_exception('error');

        $this->expectException(\moodle_exception::class);
        testable_license_region::is_european();
    }

    /**
     * MDL-INT-039: the answer is asked for again once it has been kept long enough.
     */
    public function test_the_answer_is_refreshed_after_its_time(): void {
        testable_license_region::$european = false;
        testable_license_region::is_european();
        $this->let_the_answer_go_stale();
        testable_license_region::$european = true;

        $this->assertTrue(testable_license_region::is_european());
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: forgetting the region makes the next request resolve it again.
     */
    public function test_forgetting_the_region_asks_again(): void {
        testable_license_region::$european = true;
        testable_license_region::is_european();

        license_region::forget();

        $this->assertFalse(get_config('aiprovider_datacurso', license_region::REGION));
        $this->assertTrue(testable_license_region::is_european());
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: a site with no licence at all still resolves, and keeps the answer.
     *
     * An empty key has a fingerprint of its own, so the answer for it is not confused with the
     * answer for a licence that was configured later.
     */
    public function test_a_site_without_a_licence_key_is_still_answered_for(): void {
        unset_config('licensekey', 'aiprovider_datacurso');
        testable_license_region::$european = false;

        $this->assertFalse(testable_license_region::is_european());
        $this->assertFalse(testable_license_region::is_european());

        set_config('licensekey', 'DC-A-LICENCE', 'aiprovider_datacurso');
        testable_license_region::$european = true;

        $this->assertTrue(testable_license_region::is_european());
        $this->assertSame(2, testable_license_region::$asked);
    }

    /**
     * MDL-INT-039: forgetting a region that was never resolved is not an error.
     */
    public function test_forgetting_a_region_that_was_never_resolved_is_harmless(): void {
        license_region::forget();

        $this->assertFalse(get_config('aiprovider_datacurso', license_region::REGION));
        $this->assertFalse(get_config('aiprovider_datacurso', license_region::FINGERPRINT));
        $this->assertFalse(get_config('aiprovider_datacurso', license_region::CHECKED));
    }

    /**
     * MDL-INT-039: the client that every plugin builds answers through this resolution.
     */
    public function test_the_api_client_answers_through_the_resolution(): void {
        set_config(license_region::REGION, '1', 'aiprovider_datacurso');
        set_config(license_region::FINGERPRINT, sha1('DC-A-LICENCE'), 'aiprovider_datacurso');
        set_config(license_region::CHECKED, time(), 'aiprovider_datacurso');
        $client = new \aiprovider_datacurso\httpclient\ai_services_api();

        $this->assertTrue($client->is_for_ue());
    }
}
