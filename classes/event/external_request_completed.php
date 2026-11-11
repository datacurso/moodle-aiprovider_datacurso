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

namespace aiprovider_datacurso\event;

/**
 * A request to an external Datacurso service was completed, failed or refused.
 *
 * One event is logged per external operation. Its data is restricted to an allowlist: which client
 * and service, the HTTP method, the endpoint path (never its query string), the outcome, the HTTP
 * status and an error code. It never carries the license key, headers, payload, prompt, file
 * names, response body, site URL, the user pseudonym or transport error texts.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string client: ai_services, ai_course, shop, core_ai or other.
 *      - string service: Service the request belongs to, for example local_forum_ai.
 *      - string method: HTTP method, or UPLOAD for a multipart upload.
 *      - string endpoint: Endpoint path, without query string.
 *      - string result: success, failure or refused (nothing left the site).
 *      - int httpstatus: HTTP status of the answer, 0 when there was none.
 *      - string errorcode: (only for failure and refused) Error code of the exception or the answer.
 * }
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_request_completed extends \core\event\base {
    /** @var string The request reached the service and was answered successfully. */
    public const RESULT_SUCCESS = 'success';

    /** @var string The request was attempted and failed. */
    public const RESULT_FAILURE = 'failure';

    /** @var string The request was refused locally: nothing left the site. */
    public const RESULT_REFUSED = 'refused';

    /** @var string[] Clients that can make an external request. */
    public const CLIENTS = ['ai_services', 'ai_course', 'shop', 'core_ai', 'other'];

    /** @var string[] Keys every event must carry in other. */
    private const REQUIRED_KEYS = ['client', 'service', 'method', 'endpoint', 'result', 'httpstatus'];

    /** @var string[] Keys an event may carry in other besides the required ones. */
    private const OPTIONAL_KEYS = ['errorcode'];

    #[\Override]
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    #[\Override]
    public static function get_name() {
        return get_string('event_external_request_completed', 'aiprovider_datacurso');
    }

    #[\Override]
    public function get_description() {
        $description = "The user with id '{$this->userid}' made a {$this->other['method']} request to the endpoint "
            . "'{$this->other['endpoint']}' of the {$this->other['client']} client for the service "
            . "'{$this->other['service']}'. Result: {$this->other['result']} (HTTP {$this->other['httpstatus']})";
        if (isset($this->other['errorcode'])) {
            $description .= ", error code '{$this->other['errorcode']}'";
        }
        return $description . '.';
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();

        $other = is_array($this->other) ? $this->other : [];
        $unknown = array_diff(array_keys($other), self::REQUIRED_KEYS, self::OPTIONAL_KEYS);
        if ($unknown) {
            throw new \coding_exception('Keys not allowed in other: ' . implode(', ', $unknown));
        }
        $missing = array_diff(self::REQUIRED_KEYS, array_keys($other));
        if ($missing) {
            throw new \coding_exception('Keys missing from other: ' . implode(', ', $missing));
        }
        if (!in_array($other['client'], self::CLIENTS, true)) {
            throw new \coding_exception('Unknown client in other.');
        }
        $results = [self::RESULT_SUCCESS, self::RESULT_FAILURE, self::RESULT_REFUSED];
        if (!in_array($other['result'], $results, true)) {
            throw new \coding_exception('Unknown result in other.');
        }
        if (!is_int($other['httpstatus'])) {
            throw new \coding_exception('The httpstatus in other must be an integer.');
        }
        if (strpbrk((string) $other['endpoint'], '?#') !== false) {
            throw new \coding_exception('The endpoint in other must not carry a query string.');
        }
        if ($other['result'] !== self::RESULT_SUCCESS && empty($other['errorcode'])) {
            throw new \coding_exception('A failed or refused request must carry an errorcode in other.');
        }
    }

    #[\Override]
    public static function get_other_mapping() {
        // Nothing in other refers to a database record.
        return false;
    }
}
