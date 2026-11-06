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
 * Settings extension of a fake suite plugin, used to test how the provider finds and uses extensions.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_fakesuite\aiprovider;

/**
 * Fake extension that adds one text field, rejects the value 'bad' and remembers what it saved.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings_extension implements \aiprovider_datacurso\local\plugin_settings_extension {
    /** @var \stdClass|null Last data passed to save(). */
    public static ?\stdClass $saved = null;

    /**
     * Add the fake field to the form.
     *
     * @param \MoodleQuickForm $mform Form to add the fields to.
     * @return void
     */
    public function add_fields(\MoodleQuickForm $mform): void {
        $mform->addElement('text', 'fakefield', 'Fake field');
        $mform->setType('fakefield', PARAM_TEXT);
    }

    /**
     * Current value of the fake field.
     *
     * @return array<string, mixed>
     */
    public function get_data(): array {
        return ['fakefield' => 'current'];
    }

    /**
     * Reject the value 'bad'.
     *
     * @param array $data Submitted data.
     * @return array<string, string> Errors keyed by element name.
     */
    public function validate(array $data): array {
        return (($data['fakefield'] ?? '') === 'bad') ? ['fakefield' => 'Bad value'] : [];
    }

    /**
     * Remember the saved data.
     *
     * @param \stdClass $data Submitted data.
     * @return void
     */
    public function save(\stdClass $data): void {
        self::$saved = $data;
    }
}
