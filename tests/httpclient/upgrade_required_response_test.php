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
 * Tests for the exception built from a service that refuses the plugin (HTTP 426).
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\upgrade_required_response
 */
final class upgrade_required_response_test extends \advanced_testcase {
    /** Answer for a release below the minimum. */
    private const OUTDATED = '{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "local_coursegen",'
        . ' "version": "1.0.1", "minimum": "1.2.0", "string_id": "plugin_update_required", "string": "text"}}';

    /**
     * The contract value the services send for a release that is too old.
     */
    public function test_the_error_code_is_the_contract_with_the_services(): void {
        $this->assertSame('PLUGIN_UPDATE_REQUIRED', upgrade_required_response::ERROR_PLUGIN_UPDATE_REQUIRED);
    }

    /**
     * An old release is told the plugin, its version and the minimum to update to.
     */
    public function test_an_outdated_plugin_gets_the_minimum_version(): void {
        $response = new upgrade_required_response(self::OUTDATED);

        $exception = $response->to_exception();
        $message = $exception->getMessage();

        $this->assertSame('plugin_update_required', $exception->errorcode);
        $this->assertStringContainsString('local_coursegen 1.0.1', $message);
        $this->assertStringContainsString('1.2.0', $message);
        $this->assertDebuggingCalled();
    }

    /**
     * The message is chosen by error_code, not by the id or the text the service uses to translate.
     */
    public function test_the_decision_does_not_depend_on_the_string_id(): void {
        $body = '{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "local_coursegen",'
            . ' "version": "1.0.1", "minimum": "1.2.0", "string_id": "renamed_later", "string": "other"}}';
        $response = new upgrade_required_response($body);

        $exception = $response->to_exception();

        $this->assertSame('plugin_update_required', $exception->errorcode);
        $this->assertDebuggingCalled();
    }

    /**
     * A string_id alone is not enough: without the error_code the plugin asks for the latest version.
     */
    public function test_a_string_id_without_an_error_code_is_not_trusted(): void {
        $body = '{"detail": {"string_id": "plugin_update_required", "plugin": "local_coursegen",'
            . ' "version": "1.0.1", "minimum": "1.2.0"}}';
        $response = new upgrade_required_response($body);

        $exception = $response->to_exception();

        $this->assertSame('plugin_version_required', $exception->errorcode);
        $this->assertDebuggingCalled();
    }

    /**
     * The values come from the service: tags are removed and long values are cut.
     */
    public function test_the_values_are_plain_short_text(): void {
        $long = str_repeat('9', 200);
        $body = '{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "<script>alert(1)</script>local_x",'
            . ' "version": "' . $long . '", "minimum": "1.2"}}';
        $response = new upgrade_required_response($body);

        $exception = $response->to_exception();
        $message = $exception->getMessage();

        $this->assertStringNotContainsString('<script>', $message);
        $this->assertStringNotContainsString($long, $message);
        $this->assertDebuggingCalled();
    }

    /**
     * Any other answer asks for the latest version.
     *
     * @dataProvider other_answers
     * @param string $body Body of the 426 answer.
     */
    public function test_any_other_answer_asks_for_the_latest_version(string $body): void {
        $response = new upgrade_required_response($body);

        $exception = $response->to_exception();

        $this->assertSame('plugin_version_required', $exception->errorcode);
        $this->assertDebuggingCalled();
    }

    /**
     * Answers that are not a readable "update to version X".
     *
     * @return array
     */
    public static function other_answers(): array {
        return [
            'version required' => ['{"detail": {"error_code": "PLUGIN_VERSION_REQUIRED", "string": "x"}}'],
            'unknown code' => ['{"detail": {"error_code": "SOMETHING_NEW", "minimum": "1.2", "plugin": "a"}}'],
            'update without its data' => ['{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED"}}'],
            'update without the minimum' => ['{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "a"}}'],
            'data of the wrong type' => ['{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": ["a"], "minimum": 12}}'],
            'code of the wrong type' => ['{"detail": {"error_code": ["PLUGIN_UPDATE_REQUIRED"], "plugin": "a", "minimum": "1"}}'],
            'detail is text' => ['{"detail": "upgrade"}'],
            'no detail' => ['{"other": 1}'],
            'json that is not an object' => ['"upgrade"'],
            'not json' => ['<html>Upgrade Required</html>'],
            'empty body' => [''],
        ];
    }
}
