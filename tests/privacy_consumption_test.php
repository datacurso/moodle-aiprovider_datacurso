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
 * MDL-INT-005: the plugin keeps a local table {aiprovider_datacurso_consumption} storing per-user
 * consumption (user, service, action, credits, date). The privacy provider must declare it and
 * export/delete it with the rest of the user's personal data.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\privacy\provider
 */
final class privacy_consumption_test extends provider_testcase {
    /** @var \stdClass Fixture user owning several consumption rows. */
    private \stdClass $user;

    /** @var \stdClass Second user, used to prove per-user isolation. */
    private \stdClass $otheruser;

    /** @var int Number of consumption rows owned by the fixture user. */
    private const USER_ROWS = 3;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        global $DB;
        $this->user = $this->getDataGenerator()->create_user();
        $this->otheruser = $this->getDataGenerator()->create_user();

        // Several rows for the user under test: the export must contain all of them.
        for ($i = 0; $i < self::USER_ROWS; $i++) {
            $DB->insert_record('aiprovider_datacurso_consumption', (object) [
                'externalid' => 900 + $i,
                'userid' => $this->user->id,
                'service' => 'local_coursegen',
                'action' => '/course/execute',
                'credits' => 20 + $i,
                'balance' => 980 - $i,
                'timecreated' => time() - $i,
            ]);
        }

        // A row belonging to somebody else: it must never be exported nor deleted.
        $DB->insert_record('aiprovider_datacurso_consumption', (object) [
            'externalid' => 950,
            'userid' => $this->otheruser->id,
            'service' => 'local_forum_ai',
            'action' => '/forum/execute',
            'credits' => 5,
            'balance' => 500,
            'timecreated' => time(),
        ]);
    }

    /**
     * Build the export subcontext used by the provider for the consumption table.
     *
     * @return string[]
     */
    private function consumption_subcontext(): array {
        return [
            get_string('privacy:metadata:aiprovider_datacurso', 'aiprovider_datacurso'),
            get_string('privacy:metadata:aiprovider_datacurso_consumption', 'aiprovider_datacurso'),
        ];
    }

    /**
     * The privacy metadata must declare the local consumption store and its per-user fields.
     *
     * MDL-INT-005: the table must appear in the plugin privacy registry.
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
     * Exporting a user's data must include every one of their consumption records.
     *
     * MDL-INT-005: a single export_data() call per record would overwrite the previous one,
     * leaving only the last row in the export, so all rows are exported together.
     */
    public function test_export_includes_all_consumption_records(): void {
        $usercontext = \context_user::instance($this->user->id);

        $this->export_context_data_for_user($this->user->id, $usercontext, 'aiprovider_datacurso');

        $writer = writer::with_context($usercontext);
        $this->assertTrue($writer->has_any_data());

        $data = $writer->get_data($this->consumption_subcontext());
        $this->assertNotEmpty($data);
        $this->assertCount(self::USER_ROWS, $data->records);

        $exportedids = array_map(static fn($record) => (int) $record->externalid, $data->records);
        sort($exportedids);
        $this->assertSame([900, 901, 902], $exportedids);
        $this->assertNotContains(950, $exportedids);
    }

    /**
     * Deleting a user's data must remove their consumption rows and leave other users untouched.
     *
     * MDL-INT-005: the deletion is scoped by userid.
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

        $others = $DB->count_records('aiprovider_datacurso_consumption', ['userid' => $this->otheruser->id]);
        $this->assertSame(1, $others, 'Deleting one user must not touch another user rows.');
    }
}
