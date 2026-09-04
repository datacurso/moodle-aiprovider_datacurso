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

namespace aiprovider_datacurso\httpclient;

/**
 * API client that captures the outgoing request at the cURL boundary.
 *
 * Overrides execute_request() (not send_request()) so the real send_request()
 * body still executes, including rate-limit header construction via
 * ratelimiter::get_rate_limit_headers(), while no network call is made.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_header_client extends datacurso_api_base {
    /** @var string|null HTTP method the request was executed with. */
    public ?string $capturedmethod = null;

    /** @var string|null Full URL the request was executed with. */
    public ?string $capturedurl = null;

    /** @var mixed Payload the request was executed with. */
    public $capturedpayload = null;

    /** @var array Headers the request was executed with. */
    public array $capturedheaders = [];

    /** @var string JSON response body to hand back instead of calling the network. */
    public string $returns = '{}';

    #[\Override]
    protected function execute_request(\curl $curl, string $method, string $url, $payload, array $headers): ?string {
        $this->capturedmethod = $method;
        $this->capturedurl = $url;
        $this->capturedpayload = $payload;
        $this->capturedheaders = $headers;

        return $this->returns;
    }
}
