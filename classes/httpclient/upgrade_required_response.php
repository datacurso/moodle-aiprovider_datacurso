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
 * The answer of a service that refuses the plugin (HTTP 426), turned into an exception.
 *
 * What happened is read from detail.error_code, the stable code the services agree on with the
 * plugins (python_course_ai-v2, app/core/plugin_version.py): PLUGIN_UPDATE_REQUIRED means this
 * release is older than the minimum the service accepts, with the plugin, its release and the
 * minimum as plain fields. The text of the answer is never used to decide: its string_id belongs
 * to the translation and can change. Any other answer, an update answer without its data, or one
 * that cannot be read, asks the person to update to the latest version.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upgrade_required_response {
    /** Value of detail.error_code for a release that is too old. Part of the contract with the services. */
    public const ERROR_PLUGIN_UPDATE_REQUIRED = 'PLUGIN_UPDATE_REQUIRED';

    /** Longest value taken from the service for the message. */
    private const MAX_VALUE_LENGTH = 64;

    /** @var array The detail of the answer; empty when the body could not be read. */
    private array $detail;

    /**
     * Constructor.
     *
     * @param string $response Raw body of the answer.
     */
    public function __construct(string $response) {
        $this->detail = $this->read_detail($response);
    }

    /**
     * Build the exception to throw for this answer.
     *
     * @return \moodle_exception
     */
    public function to_exception(): \moodle_exception {
        $code = $this->detail['error_code'] ?? '';
        if ($code !== self::ERROR_PLUGIN_UPDATE_REQUIRED) {
            return $this->version_required();
        }

        $a = $this->build_arguments();
        if ($a->plugin === '' || $a->minimum === '') {
            return $this->version_required();
        }

        debugging('The service no longer supports this plugin release', DEBUG_DEVELOPER);
        return new \moodle_exception('plugin_update_required', 'aiprovider_datacurso', '', $a);
    }

    /**
     * The exception that asks for the latest version of the plugin.
     *
     * @return \moodle_exception
     */
    private function version_required(): \moodle_exception {
        debugging('The service requires the plugin to report its version', DEBUG_DEVELOPER);
        return new \moodle_exception('plugin_version_required', 'aiprovider_datacurso');
    }

    /**
     * Read the detail of the answer.
     *
     * @param string $response Raw body of the answer.
     * @return array The detail, or an empty array when the body is not JSON or has no detail object.
     */
    private function read_detail(string $response): array {
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        $detail = $decoded['detail'] ?? null;
        if (!is_array($detail)) {
            return [];
        }

        return $detail;
    }

    /**
     * The values of the update message.
     *
     * @return \stdClass With plugin, version and minimum as plain short text.
     */
    private function build_arguments(): \stdClass {
        $a = new \stdClass();
        $a->plugin = $this->read_field('plugin');
        $a->version = $this->read_field('version');
        $a->minimum = $this->read_field('minimum');
        return $a;
    }

    /**
     * Read one value of the detail as plain short text.
     *
     * The values come from the service, so they are cleaned before they reach the page.
     *
     * @param string $field Name of the field.
     * @return string The cleaned value, or an empty string when it is missing or is not text.
     */
    private function read_field(string $field): string {
        $value = $this->detail[$field] ?? '';
        if (!is_string($value)) {
            return '';
        }

        $clean = clean_param($value, PARAM_TEXT);
        return \core_text::substr($clean, 0, self::MAX_VALUE_LENGTH);
    }
}
