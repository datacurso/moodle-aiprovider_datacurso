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

/**
 * Test double for the Datacurso shop API client.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\httpclient;

/**
 * Returns queued responses instead of performing any network request.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_datacurso_api extends datacurso_api {
    /** @var array<int, array> Responses handed out one per get() call, in order. */
    public array $responses = [];

    /** @var array<int, array> Endpoint and parameters received on each call. */
    public array $calls = [];

    /**
     * Build the double without a license key and without touching the network.
     */
    public function __construct() {
        // Deliberately does not call the parent constructor: it requires a license key.
    }

    /**
     * Return the next queued response.
     *
     * @param string $endpoint Requested endpoint.
     * @param array $params Query parameters.
     * @return array
     */
    public function get(string $endpoint, array $params = []): array {
        $this->calls[] = ['endpoint' => $endpoint, 'params' => $params];
        return array_shift($this->responses) ?? [];
    }
}
