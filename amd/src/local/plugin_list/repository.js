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
 * Web service calls of the Datacurso plugins tab.
 *
 * @module     aiprovider_datacurso/local/plugin_list/repository
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/**
 * Enable or disable a suite plugin in the provider.
 *
 * @param {String} component Frankenstyle component name of the plugin.
 * @param {Boolean} enabled New state.
 * @param {Number|null} tenantid Scope to change: a tenant id, 0 for the site default, null for the user's tenant.
 * @returns {Promise<{enabled: Boolean, tenantid: Number}>} The saved state and its scope.
 */
export const setPluginEnabled = (component, enabled, tenantid = null) => {
    const args = {component, enabled};
    if (tenantid !== null) {
        args.tenantid = tenantid;
    }
    return fetchMany([{
        methodname: 'aiprovider_datacurso_set_plugin_enabled',
        args,
    }])[0];
};
