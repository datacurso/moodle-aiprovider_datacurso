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

namespace aiprovider_datacurso\form;

use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\plugin_settings_extension;
use aiprovider_datacurso\local\suite_plugins;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Settings of one suite plugin in the provider (native moodleform).
 *
 * Every plugin gets the "Enabled" field. When the plugin ships a settings extension
 * (`\<component>\aiprovider\settings_extension`), its fields, validation and saving are added.
 *
 * Custom data: `component` (string), the Frankenstyle name of the plugin.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_settings_form extends \moodleform {
    /**
     * Form definition: the component, the "Enabled" field and the extension fields.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'component', $this->get_component());
        $mform->setType('component', PARAM_COMPONENT);

        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('pluginsettings_enabled', 'aiprovider_datacurso'),
            get_string('pluginsettings_enabled_desc', 'aiprovider_datacurso')
        );
        $mform->setDefault('enabled', 1);

        $extension = $this->get_extension();
        if ($extension !== null) {
            $extension->add_fields($mform);
        }

        $this->add_action_buttons();
    }

    /**
     * Validate the submission with the extension of the plugin, if any.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array<string, string> Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $extension = $this->get_extension();
        if ($extension !== null) {
            $errors = array_merge($errors, $extension->validate($data));
        }
        return $errors;
    }

    /**
     * Component of the plugin whose settings the form edits.
     *
     * @return string
     */
    private function get_component(): string {
        return (string) ($this->_customdata['component'] ?? '');
    }

    /**
     * Settings extension of the plugin, or null when it has none.
     *
     * @return plugin_settings_extension|null
     */
    private function get_extension(): ?plugin_settings_extension {
        return plugin_settings::get_extension($this->get_component());
    }

    /**
     * Stop unless the component is one of the suite plugins.
     *
     * @param string $component Frankenstyle component name.
     * @return void
     * @throws \moodle_exception When the component is not in the list.
     */
    public static function require_suite_plugin(string $component): void {
        if (!suite_plugins::is_suite_plugin($component)) {
            throw new \moodle_exception('invalidsuiteplugin', 'aiprovider_datacurso', '', s($component));
        }
    }

    /**
     * Values that prefill the form: the state of the plugin plus the values of its extension.
     *
     * @param string $component Frankenstyle component name of a suite plugin.
     * @return array<string, mixed>
     */
    public static function current_data(string $component): array {
        $data = [
            'component' => $component,
            'enabled' => plugin_settings::is_enabled($component) ? 1 : 0,
        ];
        $extension = plugin_settings::get_extension($component);
        if ($extension !== null) {
            $data = array_merge($data, $extension->get_data());
        }
        return $data;
    }

    /**
     * Save the state of a plugin and the values of its extension.
     *
     * @param string $component Frankenstyle component name of a suite plugin.
     * @param \stdClass $data Submitted data from get_data().
     * @return void
     * @throws \required_capability_exception When the current user may not change the configuration.
     * @throws \moodle_exception When the component is not a suite plugin.
     */
    public static function save(string $component, \stdClass $data): void {
        require_capability('aiprovider/datacurso:manageconfig', \context_system::instance());
        self::require_suite_plugin($component);

        plugin_settings::set_enabled($component, !empty($data->enabled));

        $extension = plugin_settings::get_extension($component);
        if ($extension !== null) {
            $extension->save($data);
        }
    }
}
