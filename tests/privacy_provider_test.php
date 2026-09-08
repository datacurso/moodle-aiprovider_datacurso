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

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\external_location;
use core_privacy\tests\provider_testcase;
use aiprovider_datacurso\privacy\provider;

/**
 * Privacy provider tests for Datacurso AI provider.
 *
 * The plugin stores a local mirror of the per-user credit consumption (declared as a database
 * table and covered by tests/privacy_consumption_test.php) and sends request data to three
 * external Datacurso systems, each declared as its own external location.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\aiprovider_datacurso\privacy\provider::class)]
final class privacy_provider_test extends provider_testcase {
    /**
     * Test setup.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Build the plugin metadata collection.
     *
     * @return collection
     */
    private function get_collection(): collection {
        return provider::get_metadata(new collection('aiprovider_datacurso'));
    }

    /**
     * Return the declared metadata items of a given type, indexed by name.
     *
     * @param string $class Metadata type class name.
     * @return array<string, \core_privacy\local\metadata\types\type>
     */
    private function get_items_by_type(string $class): array {
        $items = [];
        foreach ($this->get_collection()->get_collection() as $item) {
            if ($item instanceof $class) {
                $items[$item->get_name()] = $item;
            }
        }
        return $items;
    }

    /**
     * Metadata declares the local table and the external locations.
     */
    public function test_get_metadata(): void {
        $collection = $this->get_collection();
        $this->assertInstanceOf(collection::class, $collection);
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * One external location is declared per remote Datacurso system.
     */
    public function test_declares_three_external_locations(): void {
        $locations = $this->get_items_by_type(external_location::class);

        $this->assertSame(
            ['datacurso_ai_services', 'datacurso_course_service', 'datacurso_shop'],
            array_keys($locations)
        );
    }

    /**
     * The AI services location declares every field that actually travels in the requests.
     */
    public function test_ai_services_location_declares_transferred_fields(): void {
        $locations = $this->get_items_by_type(external_location::class);
        $fields = array_keys($locations['datacurso_ai_services']->get_privacy_fields());

        $expectedfields = ['prompt', 'messages', 'model', 'n', 'size', 'userid', 'site_id', 'site_url', 'timezone',
            'lang', 'file', 'licensekey', 'ratelimit'];
        foreach ($expectedfields as $expected) {
            $this->assertContains($expected, $fields, "Field {$expected} must be declared");
        }
        $this->assertNotContains('numberimages', $fields, 'The request field is n, not numberimages');
    }

    /**
     * The course service location declares the payload, file, site identification and rate-limit fields.
     */
    public function test_course_service_location_declares_transferred_fields(): void {
        $locations = $this->get_items_by_type(external_location::class);
        $fields = array_keys($locations['datacurso_course_service']->get_privacy_fields());

        $expectedfields = ['payload', 'file', 'userid', 'site_id', 'site_url', 'timezone', 'lang', 'licensekey', 'ratelimit'];
        foreach ($expectedfields as $expected) {
            $this->assertContains($expected, $fields, "Field {$expected} must be declared");
        }
    }

    /**
     * The shop location declares the license key used to authenticate.
     */
    public function test_shop_location_declares_license_key(): void {
        $locations = $this->get_items_by_type(external_location::class);

        $this->assertSame(['licensekey'], array_keys($locations['datacurso_shop']->get_privacy_fields()));
    }

    /**
     * The local consumption table declares every column, including the external record id.
     */
    public function test_consumption_table_declares_externalid(): void {
        $tables = $this->get_items_by_type(database_table::class);

        $this->assertArrayHasKey('aiprovider_datacurso_consumption', $tables);
        $fields = array_keys($tables['aiprovider_datacurso_consumption']->get_privacy_fields());
        $this->assertContains('externalid', $fields);
        $this->assertContains('userid', $fields);
    }

    /**
     * Every language string referenced by the metadata exists.
     */
    public function test_all_metadata_strings_exist(): void {
        $stringmanager = get_string_manager();

        foreach ($this->get_collection()->get_collection() as $item) {
            $this->assertTrue(
                $stringmanager->string_exists($item->get_summary(), 'aiprovider_datacurso'),
                "Missing summary string {$item->get_summary()}"
            );
            foreach ($item->get_privacy_fields() as $field => $identifier) {
                $this->assertTrue(
                    $stringmanager->string_exists($identifier, 'aiprovider_datacurso'),
                    "Missing string {$identifier} for field {$field}"
                );
            }
        }
    }

    /**
     * The top-level privacy statement no longer claims that nothing is stored locally.
     */
    public function test_privacy_statement_reflects_local_storage(): void {
        $statement = get_string('privacy:metadata', 'aiprovider_datacurso');

        $this->assertStringNotContainsString('does not store', $statement);
    }

    /**
     * Strings for the removed rate-limit table are gone.
     */
    public function test_rate_limit_table_strings_are_removed(): void {
        $stringmanager = get_string_manager();

        $this->assertFalse($stringmanager->string_exists('privacy:metadata:aiprovider_datacurso_rlimit', 'aiprovider_datacurso'));
        $this->assertFalse(
            $stringmanager->string_exists('privacy:metadata:aiprovider_datacurso_rlimit:userid', 'aiprovider_datacurso')
        );
    }

    /**
     * With no local data, no user context is returned.
     */
    public function test_get_contexts_for_userid_is_empty(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($user->id));
    }
}
