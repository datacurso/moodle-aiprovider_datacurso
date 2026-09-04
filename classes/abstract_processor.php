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

use core\http_client;
use core_ai\process_base;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use GuzzleHttp\Psr7\Uri;

/**
 * Abstract base class for Datacurso AI provider processors.
 *
 * Defines the common structure for sending requests and handling responses
 * from the external AI service.
 *
 * @package    aiprovider_datacurso
 * @copyright  Josue
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class abstract_processor extends process_base {
    /**
     * Returns the endpoint of the specific AI service.
     *
     * @return UriInterface
     */
    abstract protected function get_endpoint(): UriInterface;

    /**
     * Builds the JSON body to be sent to the AI service.
     *
     * @param string $userid ID of the user making the request.
     * @return array Data structure to be sent as JSON.
     */
    abstract protected function build_request_body(string $userid): array;

    /**
     * Creates the HTTP Request object ready to be sent to the AI service.
     *
     * @param string $userid ID of the user.
     * @return RequestInterface HTTP request object.
     */
    abstract protected function create_request_object(string $userid): RequestInterface;

    /**
     * Handles a successful response from the AI service.
     *
     * @param ResponseInterface $response HTTP response.
     * @return array Structured data with the processed information.
     */
    abstract protected function handle_api_success(ResponseInterface $response): array;

    /**
     * Retrieves the system instruction defined for the current action.
     *
     * @return string System instruction text.
     */
    protected function get_system_instruction(): string {
        return $this->action::get_system_instruction();
    }

    /**
     * Executes the HTTP request to the external Datacurso AI service.
     *
     * @return array Processed response data, either success or error.
     */
    #[\Override]
    protected function query_ai_api(): array {
        global $USER;

        $licensekey = get_config('aiprovider_datacurso', 'licensekey');
        $userid = $this->action->get_configuration('userid') ?? $USER->id;

        $client = \core\di::get(http_client::class);

        // Forward the per-service rate-limit config so plugins-ai-server enforces the credit limit
        // for the provider's own actions (text/summary/image) and accumulates the window counter.
        // Returns an empty array when the limit is disabled for this service.
        $path = $this->get_endpoint()->getPath();
        $serviceid = \aiprovider_datacurso\local\ratelimiter::resolve_service_for_path($path);
        // Resolve the sub-action (text vs image) so the look-ahead credit estimate is correct.
        $actionkey = \aiprovider_datacurso\local\ratelimiter::resolve_action_key($serviceid, $path);
        $ratelimitheaders = (new \aiprovider_datacurso\local\ratelimiter())
            ->get_rate_limit_header_map($serviceid, $actionkey);

        try {
            $response = $client->post(
                $this->get_endpoint(),
                [
                    RequestOptions::HEADERS => array_merge(
                        [
                            'Content-Type' => 'application/json',
                            'License-Key' => $licensekey,
                        ],
                        $ratelimitheaders
                    ),
                    RequestOptions::JSON => $this->build_request_body($userid),
                    RequestOptions::HTTP_ERRORS => false,
                ]
            );
        } catch (RequestException $e) {
            return [
                'success' => false,
                'errorcode' => (int)($e->getCode() ?: 500),
                'errormessage' => $e->getMessage(),
            ];
        }

        $status = (int)$response->getStatusCode();

        if ($status === 200) {
            return $this->handle_api_success($response);
        }

        return $this->handle_api_error($response);
    }

    /**
     * Handles error responses returned by the AI service.
     *
     * @param ResponseInterface $response HTTP response from the service.
     * @return array Structured error data.
     */
    protected function handle_api_error(ResponseInterface $response): array {
        $status = (int)$response->getStatusCode();
        $body = $response->getBody()->getContents();

        $decoded = !empty($body) ? json_decode($body) : null;

        // Per-plugin rate limit exceeded: show a clear, localized message with the retry time
        // (same handling as datacurso_api_base for the other plugins).
        if ($status === 403 && isset($decoded->detail) && $decoded->detail === 'rate_limit_exceeded') {
            $resetat = (int)($decoded->reset_at ?? 0);
            $retryat = $resetat > 0
                ? userdate($resetat, get_string('strftimedatetime', 'langconfig'))
                : '';
            return [
                'success' => false,
                'errorcode' => 403,
                'errormessage' => get_string('error_ratelimit_exceeded', 'aiprovider_datacurso', $retryat),
            ];
        }

        $message = 'Unknown error';
        if (!empty($body)) {
            if (isset($decoded->error->message)) {
                $message = $decoded->error->message;
            } else {
                $message = $body;
            }
        }

        return [
            'success' => false,
            'errorcode' => $status ?: 500,
            'errormessage' => $message,
        ];
    }
}
