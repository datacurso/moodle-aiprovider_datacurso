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

namespace aiprovider_datacurso\external;

use aiprovider_datacurso\local\plugin_settings;
use aiprovider_datacurso\local\suite_plugins;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service behind the "Enabled" switch of the Datacurso plugins tab.
 *
 * Mirrors core_ai\external\set_action: it requires the manage capability at system context, changes the
 * state through plugin_settings::set_enabled() and queues the same "{plugin} enabled/disabled" notification
 * the core AI tables show. Only installed suite plugins have a switch, so anything else is refused.
 *
 * @package    aiprovider_datacurso
 * @category   external
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_plugin_enabled extends external_api {
    /**
     * Describes the parameters of the service.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component name of the suite plugin'),
            'enabled' => new external_value(PARAM_BOOL, 'New state of the plugin'),
        ]);
    }

    /**
     * Enable or disable an installed suite plugin.
     *
     * @param string $component Frankenstyle component name of the suite plugin.
     * @param bool $enabled New state of the plugin.
     * @return array{enabled: bool} The saved state.
     * @throws \required_capability_exception When the user cannot change the provider configuration.
     * @throws \moodle_exception When the component is not a suite plugin or is not installed.
     */
    public static function execute(string $component, bool $enabled): array {
        [
            'component' => $component,
            'enabled' => $enabled,
        ] = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'enabled' => $enabled,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('aiprovider/datacurso:manageconfig', $context);

        $plugin = suite_plugins::get($component);
        if ($plugin === null) {
            throw new \moodle_exception('invalidsuiteplugin', 'aiprovider_datacurso', '', s($component));
        }
        $name = get_string($plugin['namestring'], 'aiprovider_datacurso');
        if (!suite_plugins::is_installed($component)) {
            throw new \moodle_exception('pluginnotinstalled', 'aiprovider_datacurso', '', $name);
        }

        plugin_settings::set_enabled($component, $enabled);

        \core\notification::add(
            get_string($enabled ? 'plugin_enabled' : 'plugin_disabled', 'core_admin', $name),
            \core\notification::SUCCESS
        );

        return ['enabled' => plugin_settings::is_enabled($component)];
    }

    /**
     * Describes the value returned by the service.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'State of the plugin after the change'),
        ]);
    }
}
