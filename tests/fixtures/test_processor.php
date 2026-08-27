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

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Minimal concrete processor used to unit-test {@see abstract_processor::query_ai_api()}
 * in isolation, with a fixed endpoint so no other httpclient class (and therefore no
 * network access) is involved.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class test_processor extends abstract_processor {
    #[\Override]
    protected function get_endpoint(): UriInterface {
        return new Uri('https://example.invalid/provider/chat/completions');
    }

    #[\Override]
    protected function build_request_body(string $userid): array {
        return ['userid' => $userid];
    }

    #[\Override]
    protected function create_request_object(string $userid): RequestInterface {
        return new Request('POST', $this->get_endpoint(), [], json_encode($this->build_request_body($userid)));
    }

    #[\Override]
    protected function handle_api_success(ResponseInterface $response): array {
        return [
            'success' => true,
            'generatedcontent' => (string) $response->getBody(),
        ];
    }
}
