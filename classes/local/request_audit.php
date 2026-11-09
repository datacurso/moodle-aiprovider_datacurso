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
 * Records the outcome of one request to an external Datacurso service in the Moodle log.
 *
 * Built with the description of the request before it is sent, then told once how it ended.
 * Logging is fail-open: a problem while creating or triggering the event is reported to
 * developers and never breaks the request.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request_audit {
    /** @var string Client of the Datacurso AI services. */
    public const CLIENT_AI_SERVICES = 'ai_services';

    /** @var string Client of the Datacurso course service. */
    public const CLIENT_AI_COURSE = 'ai_course';

    /** @var string Client of the Datacurso shop. */
    public const CLIENT_SHOP = 'shop';

    /** @var string Processors of the Moodle core AI actions. */
    public const CLIENT_CORE_AI = 'core_ai';

    /** @var string Any other client built on the base API client. */
    public const CLIENT_OTHER = 'other';

    /** @var string Endpoint path, without query string. */
    private string $endpoint;

    /**
     * Describe the request about to be sent.
     *
     * @param string $client One of the CLIENT_* constants.
     * @param string $service Service the request belongs to, for example local_forum_ai.
     * @param string $method HTTP method, or UPLOAD for a multipart upload.
     * @param string $endpoint Endpoint; its query string is dropped.
     * @param \context|null $context Context the request was made in; the system context when null.
     * @param int|null $relateduserid User the request was made for, when not the current user.
     */
    public function __construct(
        /** @var string One of the CLIENT_* constants. */
        private string $client,
        /** @var string Service the request belongs to. */
        private string $service,
        /** @var string HTTP method. */
        private string $method,
        string $endpoint,
        /** @var \context|null Context the request was made in. */
        private ?\context $context = null,
        /** @var int|null User the request was made for. */
        private ?int $relateduserid = null
    ) {
        $this->endpoint = self::normalise_endpoint($endpoint);
    }

    /**
     * Log that the service answered successfully.
     *
     * @param int $httpstatus HTTP status of the answer.
     */
    public function succeeded(int $httpstatus): void {
        $this->trigger(external_request_completed::RESULT_SUCCESS, $httpstatus, null);
    }

    /**
     * Log that the request was attempted and failed.
     *
     * @param \Throwable|string $error Exception raised, or the error code when there is none.
     * @param int $httpstatus HTTP status of the answer, 0 when there was none.
     */
    public function failed(\Throwable|string $error, int $httpstatus = 0): void {
        $errorcode = is_string($error) ? $error : self::error_code($error);
        $this->trigger(external_request_completed::RESULT_FAILURE, $httpstatus, $errorcode);
    }

    /**
     * Log that the request was refused before anything left the site.
     *
     * @param \Throwable $error Exception that refused it.
     */
    public function refused(\Throwable $error): void {
        $this->trigger(external_request_completed::RESULT_REFUSED, 0, self::error_code($error));
    }

    /**
     * The code that identifies an exception without its message.
     *
     * @param \Throwable $error Exception raised.
     * @return string The Moodle error code, or the short class name of any other exception.
     */
    public static function error_code(\Throwable $error): string {
        if ($error instanceof \moodle_exception && $error->errorcode !== '') {
            return $error->errorcode;
        }
        $class = get_class($error);
        return substr($class, strrpos('\\' . $class, '\\'));
    }

    /**
     * The endpoint without its query string or fragment.
     *
     * @param string $endpoint Endpoint as requested.
     * @return string
     */
    public static function strip_query(string $endpoint): string {
        return preg_replace('/[?#].*$/s', '', $endpoint);
    }

    /**
     * The endpoint as it is logged: without its query string, and with identifiers in its path replaced by {id}.
     *
     * A path segment made of digits, a UUID, a long hexadecimal string or a long token mixing
     * letters and digits (such as a session id) identifies a record or a session, not a route, so
     * it is left out of the log. Route names, including versions such as v1, are kept.
     *
     * @param string $endpoint Endpoint as requested.
     * @return string
     */
    public static function normalise_endpoint(string $endpoint): string {
        $segments = explode('/', self::strip_query($endpoint));
        foreach ($segments as $index => $segment) {
            if (self::is_identifier($segment)) {
                $segments[$index] = '{id}';
            }
        }
        return implode('/', $segments);
    }

    /**
     * Whether a path segment is an identifier rather than the name of a route.
     *
     * @param string $segment One segment of the path.
     * @return bool
     */
    private static function is_identifier(string $segment): bool {
        if ($segment === '') {
            return false;
        }
        if (ctype_digit($segment)) {
            return true;
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $segment)) {
            return true;
        }
        if (preg_match('/^[0-9a-f]{16,}$/i', $segment)) {
            return true;
        }
        return strlen($segment) >= 16 && preg_match('/^[A-Za-z0-9_-]+$/', $segment)
            && preg_match('/[0-9]/', $segment) && preg_match('/[A-Za-z]/', $segment);
    }

    /**
     * The user a request is made for, from the user id a caller handed over.
     *
     * Only a raw Moodle id names a user; a pseudonym is never reversed. The current user is the
     * author of the event, so naming it again as related user is left out.
     *
     * @param mixed $userid User id handed over by the caller: raw id, pseudonym or nothing.
     * @return int|null
     */
    public static function related_userid(mixed $userid): ?int {
        global $USER;

        $israwid = is_int($userid) || (is_string($userid) && ctype_digit($userid) && !outbound_privacy::is_pseudonym($userid));
        if (!$israwid || (int) $userid <= 0 || (int) $userid === (int) ($USER->id ?? 0)) {
            return null;
        }
        return (int) $userid;
    }

    /**
     * Create and trigger the event, never letting a logging problem reach the request.
     *
     * @param string $result One of the external_request_completed::RESULT_* constants.
     * @param int $httpstatus HTTP status of the answer, 0 when there was none.
     * @param string|null $errorcode Error code, for failure and refused.
     */
    private function trigger(string $result, int $httpstatus, ?string $errorcode): void {
        $other = [
            'client' => $this->client,
            'service' => $this->service,
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'result' => $result,
            'httpstatus' => $httpstatus,
        ];
        if ($errorcode !== null) {
            $other['errorcode'] = $errorcode;
        }

        try {
            external_request_completed::create([
                'context' => $this->context ?? \context_system::instance(),
                'relateduserid' => $this->relateduserid,
                'other' => $other,
            ])->trigger();
        } catch (\Throwable $e) {
            debugging('The external request could not be logged: ' . get_class($e), DEBUG_DEVELOPER);
        }
    }
}
