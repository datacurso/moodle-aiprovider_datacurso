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
 * API client that captures the request instead of sending it.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_upload_client extends datacurso_api_base {
    /** @var string|null Method the request was sent with. */
    public ?string $method = null;

    /** @var string|null Endpoint the request was sent to. */
    public ?string $path = null;

    /** @var array Payload the request was sent with. */
    public array $payload = [];

    /** @var string|null Path of the temporary copy at request time. */
    public ?string $temppath = null;

    /** @var string|null Content of the temporary copy at request time. */
    public ?string $contentatrequest = null;

    /** @var \Throwable|null Exception to throw instead of returning. */
    public ?\Throwable $failwith = null;

    #[\Override]
    protected function send_request(string $method, string $path, $payload = [], array $headers = []): ?array {
        $this->method = $method;
        $this->path = $path;
        $this->payload = $payload;

        if (isset($payload['file']) && $payload['file'] instanceof \CURLFile) {
            $this->temppath = $payload['file']->getFilename();
            $this->contentatrequest = file_get_contents($this->temppath);
        }

        if ($this->failwith !== null) {
            throw $this->failwith;
        }

        return ['status' => 'ok'];
    }
}
