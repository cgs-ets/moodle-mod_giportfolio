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
 * Bulk actions for the giportfolio students report table.
 *
 * @module     mod_giportfolio/bulk_actions
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CheckboxToggleAll from 'core/checkbox-toggleall';

/**
 * Initialise bulk actions.
 *
 * @param {String} formId The form element id.
 */
export const init = (formId) => {
    // Ensure CheckboxToggleAll listeners are registered.
    CheckboxToggleAll.init();

    const form = document.getElementById(formId);
    if (!form) {
        return;
    }

    const actionSelect = form.querySelector('#formactionid');
    if (!actionSelect) {
        return;
    }

    // Handle the bulk action select change.
    actionSelect.addEventListener('change', (e) => {
        const action = e.target.value;
        if (!action) {
            return;
        }

        // For download actions (URLs), POST selected user ids to avoid URL length limits.
        if (action.indexOf('#') === -1 && action !== '') {
            e.preventDefault();

            // Collect selected user ids.
            const checkboxes = form.querySelectorAll(
                'input[data-togglegroup="students-table"][data-toggle="slave"]:checked'
            );

            if (!checkboxes.length) {
                actionSelect.value = '';
                return;
            }

            // Parse the action URL to extract base URL and existing params (e.g. id, dataformat).
            const actionUrl = new URL(action, window.location.origin);

            // Build a temporary form that POSTs to download.php to avoid URI length limits.
            const downloadForm = document.createElement('form');
            downloadForm.method = 'post';
            downloadForm.action = actionUrl.pathname;

            // Forward all query-string params (id, dataformat, etc.) as hidden inputs.
            actionUrl.searchParams.forEach((value, key) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                downloadForm.appendChild(input);
            });

            // Append one hidden input per selected user.
            checkboxes.forEach(checkbox => {
                const userid = checkbox.getAttribute('name').replace('user', '');
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'userid[]';
                input.value = userid;
                downloadForm.appendChild(input);
            });

            document.body.appendChild(downloadForm);
            downloadForm.submit();
            actionSelect.value = '';
        }
    });
};
