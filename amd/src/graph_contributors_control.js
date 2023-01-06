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
 *
 * @module     mod/mod_giportfolio
 * @package    mod_giportfolio
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import jQuery from 'jquery';
import Ajax from 'core/ajax';

const Selectors = {
    headers: document.querySelectorAll('th.ch-title'), // This controls the collapsing.
    collapse: 'collapse-chapter-column',
    expand: 'collapse-chapter-column',
    headersContainer: document.querySelectorAll('.rotated-text-container') // Includes the div I need to call WS.

};

export const init = (chaptersubchapmap) => {

    Selectors.chaptersMap = JSON.parse(chaptersubchapmap);

    const initListeners = () => {
        Selectors.headers.forEach(chapter => {

            chapter.addEventListener('click', function (e) {
                e.preventDefault();

                if (!e.target.classList.contains("fa-caret-left") &&
                    !e.target.classList.contains("fa-caret-right") &&
                    !e.target.classList.contains("fa-minus") &&
                    !e.target.classList.contains("fa-plus")) {

                    return;
                }

                // Get the column number from the class list.
                // Class cX where X is the column number

                const myRe = /c[0-9]+/g;
                const myArray = myRe.exec(this.classList);
                const colIndex = myArray[0].split('c');

                if (e.target.classList.contains("fa-caret-left")) {
                    collapse(e.target);
                } else if (e.target.classList.contains("fa-caret-right")) {
                    expand(e.target);
                } else if (e.target.classList.contains("fa-minus")) {
                    hide(colIndex[colIndex.length - 1], this.firstElementChild);
                    e.target.classList.remove("fa-minus");
                    e.target.classList.add("fa-plus");
                } else {
                    show(colIndex[colIndex.length - 1], this.firstElementChild);
                    e.target.classList.remove("fa-plus");
                    e.target.classList.add("fa-minus");
                }

            });
        });

        Selectors.headersContainer.forEach(chapter => {
            chapter.addEventListener('click', function (e) {
                e.preventDefault();
                if (e.target.classList.contains('has-been-filtered')) { // Has been filtered before. Unfilter
                    unfilterTable();
                    e.target.classList.remove('has-been-filtered');
                } else {

                    const chapterid = e.target.getAttribute('data-chapterid');
                    const giportfolioid = document.getElementById('graphcontributors').getAttribute('data-giportfolio');
                    Ajax.call([{

                        methodname: 'mod_giportfolio_filter_student_with_contribution',
                        args: {
                            chapterid: chapterid,
                            giportfolioid: giportfolioid
                        },
                        done: function (response) {
                            const studentids = JSON.parse(response.studentswithcontributions);
                            filterTable(studentids);

                        },
                    }]);

                    // Add a class to the element to "unfilter" if the user clicks on it again.
                    e.target.classList.add('has-been-filtered');
                }

            });
        });
    };

    const unfilterTable = () => {
        const graphTableRows = document.querySelectorAll('#graphcontributors tbody > tr'); // Get the row of students
        graphTableRows.forEach(row => {
            if (!row.classList.contains('emptyrow')) {
                row.classList.remove('filtered');
            }
        });
    };

    const filterTable = (studentids) => {
        const graphTableRows = document.querySelectorAll('#graphcontributors tbody > tr'); // Get the row of students
        graphTableRows.forEach(row => {
            if (!row.classList.contains('emptyrow')) {
                let studentid = (row.classList[0]).split('-');
                studentid = parseInt(studentid[studentid.length - 1]);
                if (!studentids.includes(studentid)) {
                    row.classList.add('filtered');
                }
            }
        });
    }

    const collapse = (target) => {
        target.parentElement.setAttribute('hidden', true);
        target.parentElement.nextElementSibling.removeAttribute('hidden');
        collapseSubchapters(target.getAttribute("data-chapterid"));
    };

    const collapseSubchapters = (chapterid) => {

        // Hide headers
        Selectors.chaptersMap.forEach((ch) => {
            if (ch.chapterid == chapterid) {
                ch.subchapters.forEach(sch => {

                    let columnIndex = document.getElementById(sch).closest('th').cellIndex;
                    if (document.getElementById(sch).closest('th').classList.contains(Selectors.expand)) {
                        document.getElementById(sch).closest('th').classList.remove(Selectors.expand);
                    }
                    document.getElementById(sch).closest('th').classList.add(Selectors.collapse);
                    collapseColumn(columnIndex);
                });
            }
        });

    };

    const collapseColumn = (colIndex) => {
        const t = document.querySelector("#graphcontributors tbody");
        if (t) {
            Array.from(t.rows).forEach((tr) => {

                if ((tr.cells[colIndex]).classList.contains(Selectors.expand)) {
                    (tr.cells[colIndex]).classList.remove(Selectors.expand);
                }
                (tr.cells[colIndex]).classList.add(Selectors.collapse);

            }, colIndex);
        }
    };

    const expand = (target) => {
        target.parentElement.setAttribute('hidden', true);
        target.parentElement.previousElementSibling.removeAttribute('hidden');
        expandSubchapters(target.getAttribute("data-chapterid"));

    };

    const expandSubchapters = (chapterid) => {
        Selectors.chaptersMap.forEach((ch) => {
            if (ch.chapterid == chapterid) {
                ch.subchapters.forEach(sch => {

                    let columnIndex = document.getElementById(sch).closest('th').cellIndex;
                    document.getElementById(sch).closest('th').classList.remove(Selectors.collapse);

                    expandColumn(columnIndex);
                });
            }
        });
    };

    const expandColumn = (colIndex) => {
        const t = document.querySelector("#graphcontributors tbody");
        if (t) {
            Array.from(t.rows).forEach((tr) => {

                (tr.cells[colIndex]).classList.remove(Selectors.collapse);

            }, colIndex);
        }
    };

    const hide = (colIndex, chaptertitle) => {
        const t = document.querySelector("#graphcontributors tbody");
        if (t) {

            Array.from(t.rows).forEach((tr) => {
                jQuery(tr.cells[colIndex]).children().hide();

            }, colIndex);

            jQuery(chaptertitle).hide();
        }
    };

    const show = (colIndex, chaptertitle) => {
        const t = document.querySelector("#graphcontributors tbody");
        if (t) {

            Array.from(t.rows).forEach((tr) => {
                jQuery(tr.cells[colIndex]).children().show();


            }, colIndex);

            jQuery(chaptertitle).show();
        }
    };

    initListeners();
};