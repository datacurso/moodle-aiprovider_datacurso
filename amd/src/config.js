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
 * Rate limit configuration form: collects per-plugin values and saves them via the web service.
 *
 * @module     aiprovider_datacurso/config
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import {saveRateLimitConfig} from 'aiprovider_datacurso/repository';

const SELECTORS = {
    root: '[data-region="aiprovider_datacurso/config-root"]',
    saveBtn: '[data-action="save-ratelimit-config"]',
    serviceRow: '[data-service]',
    field: (name) => `[data-field="${name}"]`,
};

/**
 * Read every service row into the payload expected by the web service.
 *
 * @param {HTMLElement} root
 * @returns {Array}
 */
const collect = (root) => {
    const rows = root.querySelectorAll(SELECTORS.serviceRow);
    const services = [];
    rows.forEach((row) => {
        const val = (name) => {
            const el = row.querySelector(SELECTORS.field(name));
            return el ? el.value : '';
        };
        const enableEl = row.querySelector(SELECTORS.field('enable'));
        // Credits per action: one input per sub-action, each tagged with data-actionkey.
        const creditsperaction = [];
        row.querySelectorAll('[data-actionkey]').forEach((el) => {
            creditsperaction.push({
                key: el.getAttribute('data-actionkey'),
                value: parseInt(el.value, 10) || 0,
            });
        });
        services.push({
            sid: row.getAttribute('data-service'),
            enable: (enableEl && enableEl.checked) ? 1 : 0,
            limit: parseInt(val('limit'), 10) || 0,
            windowvalue: parseInt(val('windowvalue'), 10) || 1,
            windowunit: val('windowunit') || 'hours',
            creditsperaction: creditsperaction,
        });
    });
    return services;
};

/**
 * Initialise the configuration form.
 */
export const init = () => {
    const root = document.querySelector(SELECTORS.root);
    if (!root) {
        return;
    }
    const saveBtn = root.querySelector(SELECTORS.saveBtn);
    if (!saveBtn) {
        return;
    }

    saveBtn.addEventListener('click', async() => {
        saveBtn.disabled = true;
        try {
            const services = collect(root);
            await saveRateLimitConfig(services);
            const msg = await getString('config_saved', 'aiprovider_datacurso');
            Notification.addNotification({message: msg, type: 'success'});
        } catch (error) {
            Notification.exception(error);
        } finally {
            saveBtn.disabled = false;
        }
    });
};
