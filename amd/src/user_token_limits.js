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

// AMD module to handle delete confirmation in the user token limits page.
// @package    aiprovider_datacurso
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

import Notification from "core/notification";
import * as Str from "core/str";
import { deleteUserTokenLimit } from "aiprovider_datacurso/repository";

export const init = () => {
    const root = document.querySelector('[data-region="aiprovider-datacurso-userlimits"]');
    if (!root) {
        return;
    }

    root.addEventListener("click", async (e) => {
        const link = e.target.closest('[data-action="delete"]');
        if (!link) {
            return;
        }
        e.preventDefault();
        const id = link.dataset.id;
        const username = link.dataset.username || "";

        const [title, message, yes, no] = await Str.get_strings([
            { key: "confirm_delete_title", component: "aiprovider_datacurso" },
            {
                key: "confirm_delete_message",
                component: "aiprovider_datacurso",
                param: username,
            },
            { key: "yes" },
            { key: "no" },
        ]);

        Notification.confirm(title, message, yes, no, async () => {
            try {
                const result = await deleteUserTokenLimit(id);
                if (result && result.success) {
                    window.location.reload();
                    return;
                }
                const err = await Str.get_string(
                    "usertokenlimit_delete_failed",
                    "aiprovider_datacurso"
                );
                Notification.alert(
                    title,
                    result && result.message ? result.message : err
                );
            } catch (ex) {
                const err = await Str.get_string(
                    "usertokenlimit_delete_failed",
                    "aiprovider_datacurso"
                );
                Notification.alert(title, err);
            }
        });
    });
};
