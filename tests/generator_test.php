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

namespace aiprovider_datacurso;

/**
 * Tests for the aiprovider_datacurso component data generator.
 *
 * This is also the closest local PHPUnit-executable coverage for the Behat generator
 * ({@see \behat_aiprovider_datacurso_generator}), which delegates directly to
 * {@see \aiprovider_datacurso_generator::create_consumption()} and cannot itself be exercised
 * without a running Behat/Selenium environment.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso_generator::class)]
final class generator_test extends \advanced_testcase {
    /**
     * Get the plugin's own data generator.
     *
     * @return \aiprovider_datacurso_generator
     */
    protected function generator(): \aiprovider_datacurso_generator {
        /** @var \aiprovider_datacurso_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('aiprovider_datacurso');
        return $generator;
    }

    /**
     * A record created with no fields at all gets sensible defaults for every column,
     * and externalid auto-increments across successive calls on the same generator.
     */
    public function test_create_consumption_fills_in_defaults(): void {
        global $DB;
        $this->resetAfterTest();

        $first = $this->generator()->create_consumption([]);
        $second = $this->generator()->create_consumption([]);

        $this->assertSame(1, $first->externalid);
        $this->assertSame(2, $second->externalid);
        $this->assertSame(0, $first->userid);
        $this->assertSame('', $first->service);
        $this->assertSame('', $first->action);
        $this->assertEquals(0, $first->credits);
        $this->assertEquals(0, $first->balance);
        $this->assertGreaterThan(0, $first->timecreated);

        $stored = $DB->get_record('aiprovider_datacurso_consumption', ['id' => $first->id]);
        $this->assertEquals($first->externalid, $stored->externalid);
    }

    /**
     * An explicit externalid is kept, and later auto-generated ids continue counting up from it.
     */
    public function test_explicit_externalid_is_kept_and_counter_continues_from_it(): void {
        $this->resetAfterTest();

        $explicit = $this->generator()->create_consumption(['externalid' => 500]);
        $next = $this->generator()->create_consumption([]);

        $this->assertSame(500, $explicit->externalid);
        $this->assertSame(501, $next->externalid);
    }

    /**
     * A non-numeric userid is resolved as a username lookup against the {user} table.
     */
    public function test_userid_given_as_username_is_resolved_to_the_user_id(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['username' => 'consumptionowner']);

        $record = $this->generator()->create_consumption(['userid' => 'consumptionowner']);

        $this->assertSame((int) $user->id, $record->userid);
    }

    /**
     * A numeric userid (whether int or numeric string) is used as-is, with no lookup.
     */
    public function test_numeric_userid_is_used_as_is(): void {
        $this->resetAfterTest();

        $record = $this->generator()->create_consumption(['userid' => '42']);

        $this->assertSame(42, $record->userid);
    }

    /**
     * timecreated accepts a plain int timestamp unchanged.
     */
    public function test_timecreated_accepts_an_int_timestamp(): void {
        $this->resetAfterTest();

        $timestamp = 1700000000;
        $record = $this->generator()->create_consumption(['timecreated' => $timestamp]);

        $this->assertSame($timestamp, $record->timecreated);
    }

    /**
     * timecreated accepts a parseable date string, including Moodle's Behat "##...##"
     * relative-date convention with the hashes stripped before parsing.
     */
    public function test_timecreated_accepts_a_parseable_date_string(): void {
        $this->resetAfterTest();

        $record = $this->generator()->create_consumption(['timecreated' => '2026-01-15']);
        $this->assertSame(strtotime('2026-01-15'), $record->timecreated);

        $hashed = $this->generator()->create_consumption(['timecreated' => '##2026-02-10##']);
        $this->assertSame(strtotime('2026-02-10'), $hashed->timecreated);

        $relative = $this->generator()->create_consumption(['timecreated' => '##yesterday##']);
        $this->assertSame(strtotime('yesterday'), $relative->timecreated);
    }
}
