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
 * Test double for Moodle's \curl wrapper.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\httpclient;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Returns queued responses (body, HTTP code, cURL error/errno) instead of performing any network request.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_curl extends \curl {
    /** @var array<int, array{body: string, http_code: int, error: string, errno: int}> Queued responses. */
    public array $queue = [];

    /** @var array<int, array{method: string, url: string, params: mixed, options: array}> Calls received. */
    public array $calls = [];

    /**
     * Queue a response to be handed out on the next request.
     *
     * @param string $body Response body.
     * @param int $httpcode HTTP status code reported through get_info().
     * @param string $error cURL error text (empty for none).
     * @param int $errno cURL error number (0 for none).
     */
    public function enqueue(string $body, int $httpcode = 200, string $error = '', int $errno = 0): void {
        $this->queue[] = ['body' => $body, 'http_code' => $httpcode, 'error' => $error, 'errno' => $errno];
    }

    /**
     * Record the call and hand out the next queued response.
     *
     * @param string $method HTTP method used by the caller.
     * @param string $url Requested URL.
     * @param mixed $params Parameters passed by the caller.
     * @param array $options cURL options passed by the caller.
     * @return string
     */
    private function next(string $method, string $url, $params, array $options): string {
        $this->calls[] = ['method' => $method, 'url' => $url, 'params' => $params, 'options' => $options];
        $response = array_shift($this->queue) ?? ['body' => '', 'http_code' => 0, 'error' => '', 'errno' => 0];
        $this->info = ['http_code' => $response['http_code']];
        $this->error = $response['error'];
        $this->errno = $response['errno'];
        return $response['body'];
    }

    #[\Override]
    public function get($url, $params = [], $options = []) {
        return $this->next('GET', (string) $url, $params, $options);
    }

    #[\Override]
    public function post($url, $params = '', $options = []) {
        return $this->next('POST', (string) $url, $params, $options);
    }

    #[\Override]
    public function put($url, $params = [], $options = []) {
        return $this->next('PUT', (string) $url, $params, $options);
    }

    #[\Override]
    public function delete($url, $param = [], $options = []) {
        return $this->next('DELETE', (string) $url, $param, $options);
    }
}
