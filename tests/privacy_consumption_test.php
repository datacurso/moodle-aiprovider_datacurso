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
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use aiprovider_datacurso\privacy\provider;

/**
 * Privacy tests for the local per-user consumption store.
 *
 * MDL-INT-005 is [Pendiente:fail]: the plugin now keeps a local table
 * {aiprovider_datacurso_consumption} storing per-user consumption (user, service, action, credits,
 * date), but the privacy provider neither declares it nor exports/deletes it. The three tests below
 * assert the correct, compliant behavior and are therefore red by design until the provider is fixed.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\privacy\provider
 */
final class privacy_consumption_test extends provider_testcase {
    /** @var \stdClass Fixture user owning a consumption row. */
    private \stdClass $user;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->user = $this->getDataGenerator()->create_user();
        $DB->insert_record('aiprovider_datacurso_consumption', (object) [
            'externalid' => 900,
            'userid' => $this->user->id,
            'service' => 'local_coursegen',
            'action' => '/course/execute',
            'credits' => 20,
            'balance' => 980,
            'timecreated' => time(),
        ]);
    }

    /**
     * The privacy metadata must declare the local consumption store and its per-user fields.
     *
     * MDL-INT-005: [Pendiente:fail] the table is not declared in get_metadata.
     */
    public function test_metadata_declares_consumption_table(): void {
        $collection = provider::get_metadata(new collection('aiprovider_datacurso'));

        $declaredtables = [];
        foreach ($collection->get_collection() as $item) {
            $declaredtables[] = $item->get_name();
        }

        $this->assertContains('aiprovider_datacurso_consumption', $declaredtables);
    }

    /**
     * Exporting a user's data must include their consumption records.
     *
     * MDL-INT-005: [Pendiente:fail] nothing is exported because no table map is declared.
     */
    public function test_export_includes_consumption(): void {
        $usercontext = \context_user::instance($this->user->id);

        $this->export_context_data_for_user($this->user->id, $usercontext, 'aiprovider_datacurso');

        $this->assertTrue(writer::with_context($usercontext)->has_any_data());
    }

    /**
     * Deleting a user's data must remove their consumption rows.
     *
     * MDL-INT-005: [Pendiente:fail] the delete path is a no-op because the table map is empty.
     */
    public function test_delete_removes_consumption(): void {
        global $DB;

        $usercontext = \context_user::instance($this->user->id);
        $contextlist = new approved_contextlist(
            $this->user,
            'aiprovider_datacurso',
            [$usercontext->id]
        );

        provider::delete_data_for_user($contextlist);

        $remaining = $DB->count_records('aiprovider_datacurso_consumption', ['userid' => $this->user->id]);
        $this->assertSame(0, $remaining);
    }
}
