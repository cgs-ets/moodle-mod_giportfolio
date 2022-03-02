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
 * Reminder table functions
 * This is also used by the report/participants/index.php because it has the same functionality.
 *
 * @module     mod/mod_giportfolio
 * @package    mod_giportfolio
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CustomEvents from 'core/custom_interaction_events';
import Notification from 'core/notification';
import jQuery from 'jquery';
import Ajax from 'core/ajax';
import * as Str from 'core/str';
import URL from 'core/url';

const Selectors = {
    bulkActionSelect: "#formactionid",
    bulkUserSelectedCheckBoxes: "input[data-togglegroup='participants-table'][data-toggle='slave']:checked",
    checkCountButton: "#checkall",
    showCountText: '[data-region="participant-count"]',
    showCountToggle: '[data-action="showcount"]',
    stateHelpIcon: '[data-region="state-help-icon"]',
    tableForm: uniqueId => `form[data-table-unique-id="${uniqueId}"]`,
    modalMessages: [{
            key: 'confirm',
            component: 'mod_giportfolio'
        },
        {
            key: 'emailbody',
            component: 'mod_giportfolio'
        },
        {
            key: 'yes'
        },
        {
            key: 'no'
        },

    ]
};

export const init = ({
    uniqueid,
    chapterid,
    chapter,
    portfolio,
    portfolioid,
    course,
    cm
}) => {
    /**
     * Private method.
     *'url' =>  new moodle_url('/mod/giportfolio/viewgiportfolio.php', ['id' => $cm, 'chapterid' => $chapterid]),
     * @method registerEventListeners
     * @private
     */
    const registerEventListeners = () => {
        CustomEvents.define(Selectors.bulkActionSelect, [CustomEvents.events.accessibleChange]);
        jQuery(Selectors.bulkActionSelect).on(CustomEvents.events.accessibleChange, e => {
            const bulkActionSelect = e.target.closest('select');
            const action = bulkActionSelect.value;
            const tableRoot = document.getElementById(uniqueid);
            const checkboxes = tableRoot.querySelectorAll(Selectors.bulkUserSelectedCheckBoxes);

            const link = URL.relativeUrl('/mod/giportfolio/viewgiportfolio.php', {
                id: cm,
                chapterid: chapterid
            }, true);

            const modalMessages = [{
                    key: 'confirm',
                    component: 'mod_giportfolio'
                },
                {
                    key: 'remindernotificationmodal_body',
                    component: 'mod_giportfolio',
                    param: {
                        chapter: chapter,
                        portfolio: portfolio,                       
                        course: course,
                        link: link
                    }
                },
                {
                    key: 'send',
                    component: 'mod_giportfolio'
                },
                {
                    key: 'no'
                },

            ];

            if (action.indexOf('#') !== -1) {
                e.preventDefault();

                const ids = [];
                checkboxes.forEach(checkbox => {
                    ids.push(checkbox.getAttribute('name').replace('user', ''));
                });

                if (action === '#messageselect') {

                    Str.get_strings(modalMessages).done(function (strs) {
                        Notification.confirm(strs[0], strs[1], strs[2], strs[3], function () {

                            const reminder = document.querySelector('div.reminder');
                            reminder.removeAttribute('hidden');

                            //display animation
                                const chapterd = {
                                    chapterid,
                                    chapter,
                                    portfolio,
                                    portfolioid,
                                    course,
                                    cm: cm
                                }
                            
                                Ajax.call([{

                                    methodname: 'mod_giportfolio_send_reminder',

                                    args: {
                                        users: JSON.stringify(ids),
                                        chapter: JSON.stringify(chapterd),
                                    },

                                    done: function (response) {
                                        //Replace the closed envelopes to open ones. 
                                        updateStatusColumn(ids);
                                        //Remove animation
                                        const reminderImg = document.querySelector('img.reminder-image');
                                        jQuery(reminderImg).replaceWith(response.status);
                                        jQuery(reminder).delay(2000).fadeOut(400);
                                    },

                                    fail: function (reason) {
                                        const reminderImg = document.querySelector('img.reminder-image');
                                        jQuery(reminderImg).replaceWith('<h1>Please try again later');
                                        jQuery(reminder).delay(2000).fadeOut(400);

                                    }

                                }]);


                            },
                            function () {
                                // For the cancel btn.
                                return;
                            });
                    });

                }
            } else if (action !== '' && checkboxes.length) {
                bulkActionSelect.form.submit();
            }

            resetBulkAction(bulkActionSelect);
        });


    };

    const updateStatusColumn = (ids) => {
                            
        const t = document.querySelector('#mod-giportfolio-reminder-table tbody');

        if (t) {
          
            Array.from(t.rows).forEach((tr) => {
                let user = tr.classList.value.split("-");
                user = user[user.length - 1];
                if (ids.includes(user)) {
                    jQuery(tr.cells[3]).children().replaceWith('<span class="giportfolio-legend" title="Reminder sent"><i class="fa">&#xf2b7;</i></span>'); // Column 3 has the status. 

                }
                
            });

        }
      
    }

    const resetBulkAction = bulkActionSelect => {
        bulkActionSelect.value = '';
    };


    registerEventListeners();
};