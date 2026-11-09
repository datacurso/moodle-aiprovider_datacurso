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
 * "Enabled" switches of the Datacurso plugins tab.
 *
 * Follows core_admin/plugin_management_table: a switch calls the web service through core/ajax, then
 * the notification queued by the service is fetched. The table is static, so instead of reloading it
 * the switch, its label and the row are updated in place.
 *
 * @module     aiprovider_datacurso/plugin_list
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Selectors from './local/plugin_list/selectors';
import {setPluginEnabled} from './local/plugin_list/repository';
import {fetchNotifications, exception as displayException} from 'core/notification';
import {getStrings} from 'core/str';
import Pending from 'core/pending';

let watching = false;

/**
 * Reflect a saved state on the switch, its label and the row.
 *
 * @param {HTMLInputElement} toggle The switch.
 * @param {Boolean} enabled Saved state.
 * @returns {Promise<void>}
 */
const applyState = async(toggle, enabled) => {
    const root = toggle.closest(Selectors.regions.root);
    const row = root.querySelector(Selectors.rowFor(toggle.dataset.component));

    const [label] = await getStrings([
        {key: enabled ? 'disableplugin' : 'enableplugin', component: 'core_admin', param: toggle.dataset.name},
    ]);

    toggle.checked = enabled;
    toggle.dataset.state = enabled ? '1' : '0';
    row?.classList.toggle('dimmed_text', !enabled);

    const labelNode = root.querySelector(Selectors.labelFor(toggle.id));
    if (labelNode) {
        labelNode.setAttribute('title', label);
        labelNode.setAttribute('data-original-title', label);
        const text = labelNode.querySelector('span');
        if (text) {
            text.textContent = label;
        }
    }
};

/**
 * Save the state a switch was moved to, reverting it when the service refuses.
 *
 * @param {HTMLInputElement} toggle The switch.
 * @returns {Promise<void>}
 */
const handleToggle = async(toggle) => {
    const pendingPromise = new Pending('aiprovider_datacurso/plugin_list:toggle-enabled');
    const previous = toggle.dataset.state === '1';
    toggle.disabled = true;
    try {
        const tenantid = toggle.dataset.tenantid === undefined ? null : parseInt(toggle.dataset.tenantid, 10);
        const result = await setPluginEnabled(toggle.dataset.component, toggle.checked, tenantid);
        await applyState(toggle, result.enabled);
        await fetchNotifications();
    } catch (error) {
        await applyState(toggle, previous);
        displayException(error);
    } finally {
        toggle.disabled = false;
        toggle.focus();
        pendingPromise.resolve();
    }
};

/**
 * Listen to the switches of the tab.
 */
const registerEventListeners = () => {
    document.addEventListener('change', (e) => {
        const toggle = e.target.closest(Selectors.actions.toggleEnabled);
        if (!toggle || !toggle.closest(Selectors.regions.root)) {
            return;
        }
        handleToggle(toggle);
    });
};

/**
 * Initialise the tab once per page.
 */
export const init = () => {
    if (watching) {
        return;
    }
    watching = true;
    registerEventListeners();
};
