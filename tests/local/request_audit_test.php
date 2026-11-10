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

use aiprovider_datacurso\event\external_request_completed;

/**
 * Tests for the endpoint recorded by the audit log of external requests.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(request_audit::class)]
final class request_audit_test extends \advanced_testcase {
    /**
     * Endpoints and the form they are logged in.
     *
     * @return array
     */
    public static function endpoint_provider(): array {
        return [
            'plain route' => ['/provider/chat/completions', '/provider/chat/completions'],
            'versioned route' => ['/jedomi/v1/chat/completions', '/jedomi/v1/chat/completions'],
            'shop route' => ['tokens/saldo', 'tokens/saldo'],
            'query string' => ['/chat/stream?session_id=abc', '/chat/stream'],
            'numeric id' => ['/chat/12345/messages', '/chat/{id}/messages'],
            'trailing numeric id' => ['/course/session/42', '/course/session/{id}'],
            'uuid' => ['/chat/3f2b8c1e-9a4d-4e2f-8b7a-1c2d3e4f5a6b', '/chat/{id}'],
            'long hex' => ['/files/0123456789abcdef0123456789abcdef/info', '/files/{id}/info'],
            'opaque token' => ['/sessions/sess_8fK2pQ9xZ7LmN3vB4tY6/close', '/sessions/{id}/close'],
            'several ids' => ['/a/1/b/2', '/a/{id}/b/{id}'],
        ];
    }

    /**
     * Identifiers and session tokens in the path are replaced by {id}; route names are kept.
     *
     * @param string $endpoint Endpoint as requested.
     * @param string $expected Endpoint as logged.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('endpoint_provider')]
    public function test_normalise_endpoint(string $endpoint, string $expected): void {
        $this->assertSame($expected, request_audit::normalise_endpoint($endpoint));
    }

    /**
     * The event records the normalised endpoint.
     */
    public function test_event_records_the_normalised_endpoint(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        (new request_audit(request_audit::CLIENT_AI_SERVICES, 'local_jedomi', 'GET', '/chat/98765?session_id=x'))
            ->succeeded(200);

        $events = array_values(array_filter(
            $sink->get_events(),
            fn($event) => $event instanceof external_request_completed
        ));
        $this->assertCount(1, $events);
        $this->assertSame('/chat/{id}', $events[0]->other['endpoint']);
    }
}
