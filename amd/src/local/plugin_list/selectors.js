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
 * Selectors of the Datacurso plugins tab.
 *
 * @module     aiprovider_datacurso/local/plugin_list/selectors
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default {
    regions: {
        root: '[data-region="aiprovider_datacurso/plugin_list"]',
    },
    actions: {
        toggleEnabled: '[data-action="aiprovider_datacurso/plugin_list/toggle-enabled"]',
    },
    /**
     * Switch of one plugin.
     *
     * @param {String} component Frankenstyle component name.
     * @returns {String}
     */
    toggleFor: (component) => `[data-action="aiprovider_datacurso/plugin_list/toggle-enabled"][data-component="${component}"]`,
    /**
     * Label of a switch.
     *
     * @param {String} id Id of the switch.
     * @returns {String}
     */
    labelFor: (id) => `label[for="${id}"]`,
    /**
     * Row of one plugin.
     *
     * @param {String} component Frankenstyle component name.
     * @returns {String}
     */
    rowFor: (component) => `tr[data-component="${component}"]`,
};
