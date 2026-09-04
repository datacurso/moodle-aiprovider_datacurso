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
 * Behat data generator for aiprovider_datacurso.
 *
 * Lets feature files create consumption history rows with:
 *   Given the following "aiprovider_datacurso > consumption" records exist:
 *     | userid | service | action | credits | balance | timecreated |
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_aiprovider_datacurso_generator extends behat_generator_base {
    /**
     * Get the list of entities that can be created for aiprovider_datacurso.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'consumption' => [
                'singular' => 'consumption',
                'datagenerator' => 'consumption',
                // No field is strictly required: aiprovider_datacurso_generator::create_consumption()
                // fills externalid (auto-increment), userid, service, action, credits, balance and
                // timecreated with sensible defaults when they are omitted from the table.
                'required' => [],
            ],
        ];
    }
}
