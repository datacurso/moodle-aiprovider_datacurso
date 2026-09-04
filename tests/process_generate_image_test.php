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
 * Tests for the image generation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso;

use core_ai\aiactions\generate_image;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Tests for the image generation processor.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\process_generate_image
 */
final class process_generate_image_test extends \advanced_testcase {
    /**
     * An empty prompt is rejected locally, before any call to the AI service.
     *
     * MDL-UNIT-010: prompt validation on image generation.
     */
    public function test_empty_prompt_is_rejected_before_network(): void {
        global $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Empty MockHandler: if the processor reached the network the handler would raise a
        // different error, so requiring the moodle_exception proves the request never left.
        $stack = HandlerStack::create(new MockHandler([]));
        \core\di::set(\core\http_client::class, new \core\http_client(['handler' => $stack]));

        $action = new generate_image(
            \context_system::instance()->id,
            (int) $USER->id,
            '',
            'hd',
            'square',
            1,
            'vivid'
        );
        $processor = new class (new provider(), $action) extends process_generate_image {
            #[\Override]
            protected function get_endpoint(): UriInterface {
                return new Uri('https://example.invalid/provider/images/generations');
            }
        };

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessageMatches('/Empty prompt/');

        $processor->process();
    }
}
