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
 * API client that returns canned per-page responses instead of calling the remote API.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_consumption_api_client extends datacurso_api {
    /** @var array<int, array> Canned response keyed by the requested page number. */
    public array $responsesbypage = [];

    /** @var array<int, array> Every set of params this client was called with, in order. */
    public array $calls = [];

    #[\Override]
    public function get(string $endpoint, array $params = []): array {
        $this->calls[] = $params;
        $page = (int)($params['page'] ?? 1);

        return $this->responsesbypage[$page] ?? ['status' => 'error'];
    }
}
