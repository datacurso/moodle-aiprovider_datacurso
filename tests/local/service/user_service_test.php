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

namespace aiprovider_datacurso\local\service;

/**
 * Tests for the user lookup service behind the get_users web service.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\local\service\user_service
 */
final class user_service_test extends \advanced_testcase {
    /**
     * The search returns the matching active users with their full names.
     */
    public function test_get_users_returns_matching_users(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $alice = $generator->create_user(['firstname' => 'Alice', 'lastname' => 'Zed']);
        $generator->create_user(['firstname' => 'Bob', 'lastname' => 'Yon']);

        $result = user_service::get_users('Alice');

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $result['users']);
        $this->assertEquals($alice->id, $result['users'][0]['id']);
        $this->assertSame('Alice Zed', $result['users'][0]['fullname']);
    }

    /**
     * A database failure is reported with a localized message: driver details never reach the caller.
     */
    public function test_database_error_is_not_exposed(): void {
        $this->resetAfterTest();

        $db = $this->createMock(\moodle_database::class);
        $db->method('get_records_sql')
            ->willThrowException(new \Exception('SQLSTATE secret: relation mdl_user does not exist'));

        $result = user_service::get_users('', $db);

        $this->assertSame('error', $result['status']);
        $this->assertSame([], $result['users']);
        $this->assertSame(get_string('errorgetusers', 'aiprovider_datacurso'), $result['message']);
        $this->assertStringNotContainsString('secret', $result['message']);

        $debugmessages = $this->getDebuggingMessages();
        $this->assertDebuggingCalledCount(1);
        $this->assertStringContainsString('SQLSTATE secret', $debugmessages[0]->message);
    }
}
