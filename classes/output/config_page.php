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

namespace aiprovider_datacurso\output;

use renderable;
use templatable;
use renderer_base;
use aiprovider_datacurso\provider;

/**
 * Rate-limit configuration page (per-plugin credit limits and credits-per-action).
 *
 * Reads the current configuration and builds the context for the Mustache form. Saving is done
 * client-side via the aiprovider_datacurso_ratelimit_save_config web service.
 *
 * @package    aiprovider_datacurso
 * @category   output
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config_page implements renderable, templatable {

    /** @var string[] Selectable window units. */
    private const UNITS = ['minutes', 'hours', 'days'];

    /**
     * Export the per-plugin configuration for the template.
     *
     * @param renderer_base $output The renderer in use.
     * @return array Data to pass to the Mustache template.
     */
    public function export_for_template(renderer_base $output): array {
        $services = provider::get_services();
        \core_collator::asort_array_of_arrays_by_key($services, 'name');

        $rows = [];
        foreach ($services as $service) {
            $sid = $service['id'];

            $enable = (int) get_config('aiprovider_datacurso', "ratelimit_{$sid}_enable");
            $limit = get_config('aiprovider_datacurso', "ratelimit_{$sid}_limit");
            $limit = ($limit === false || $limit === '') ? 10 : (int) $limit;

            // Credits per action: JSON map {actionkey: credits}. Merge catalog defaults with stored values.
            $stored = json_decode((string) get_config('aiprovider_datacurso', "ratelimit_{$sid}_creditperaction"), true);
            $stored = is_array($stored) ? $stored : [];
            $actions = [];
            foreach (provider::get_actions_for_service($sid) as $action) {
                $key = $action['key'];
                $actions[] = [
                    'key' => $key,
                    'label' => (string) $action['name'],
                    'value' => isset($stored[$key]) ? (int) $stored[$key] : (int) $action['default'],
                ];
            }

            // Window is stored as {value, unit} JSON (same as before).
            $windowraw = (string) get_config('aiprovider_datacurso', "ratelimit_{$sid}_window");
            $window = json_decode($windowraw, true);
            $windowvalue = is_array($window) && (int) ($window['value'] ?? 0) > 0 ? (int) $window['value'] : 1;
            $windowunit = is_array($window) && in_array(($window['unit'] ?? ''), self::UNITS, true)
                ? $window['unit'] : 'hours';

            $units = [];
            foreach (self::UNITS as $unit) {
                $units[] = [
                    'value' => $unit,
                    // Reuses the same unit strings as the duration-unit admin setting (minutes/hours/days).
                    'label' => get_string($unit, 'aiprovider_datacurso'),
                    'selected' => ($unit === $windowunit),
                ];
            }

            $rows[] = [
                'sid' => $sid,
                'name' => format_string($service['name']),
                'enable' => ($enable === 1),
                'limit' => $limit,
                'windowvalue' => $windowvalue,
                'units' => $units,
                'actions' => $actions,
            ];
        }

        return ['services' => $rows];
    }
}
