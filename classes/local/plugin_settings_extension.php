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

namespace aiprovider_datacurso\local;

/**
 * Extra fields a suite plugin adds to its settings page in the provider.
 *
 * A suite plugin opts in by shipping the class `\<component>\aiprovider\settings_extension`
 * implementing this interface. The provider finds it by name, so it never depends on the plugin.
 * The extension keeps its values wherever the plugin reads them (usually the plugin's own config).
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface plugin_settings_extension {
    /**
     * Add the plugin fields to the settings form, after the "Enabled" field.
     *
     * @param \MoodleQuickForm $mform Form to add the fields to.
     * @return void
     */
    public function add_fields(\MoodleQuickForm $mform): void;

    /**
     * Current values of the plugin fields, keyed by element name, to prefill the form.
     *
     * @return array<string, mixed>
     */
    public function get_data(): array;

    /**
     * Validate the submitted values of the plugin fields.
     *
     * @param array $data Submitted form data.
     * @return array<string, string> Error messages keyed by element name; empty when valid.
     */
    public function validate(array $data): array;

    /**
     * Save the submitted values of the plugin fields.
     *
     * @param \stdClass $data Validated form data.
     * @return void
     */
    public function save(\stdClass $data): void;
}
