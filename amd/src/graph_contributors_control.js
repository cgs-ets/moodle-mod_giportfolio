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

import jQuery from 'jquery';
 
const Selectors = {
    headers: document.querySelectorAll('th.ch-title'),
    graph: document.getElementById('graphcontributors'),
    table: document.getElementById('graphcontributors'),
    collapse: 'collapse-chapter-column',
    expand: 'collapse-chapter-column'

}

export const init = (chaptersubchapmap) => {
    
    Selectors.graph.parentElement.classList.remove('no-overflow'); // Remove the class given by Moodle.
    Selectors.graph.parentElement.classList.add('graphcontributors-overflow'); // Add my custom class.
    Selectors.chaptersMap = JSON.parse(chaptersubchapmap);

    const initListeners = () => {
        Selectors.headers.forEach(chapter => {

            chapter.addEventListener('click', function (e) {
                e.preventDefault();
                
                if (!e.target.classList.contains("fa-caret-left")
                    && !e.target.classList.contains("fa-caret-right")
                    && !e.target.classList.contains("fa-minus")
                    && !e.target.classList.contains("fa-plus")) {
                  
                    return;
                }

                // Get the column number from the class list. 
                // Class cX where X is the column number

                const myRe = /c[0-9]+/g;
                const myArray = myRe.exec(this.classList);
                const colIndex = myArray[0].split('c');

                if (e.target.classList.contains("fa-caret-left")) {
                    collapse(e.target);
                } else if (e.target.classList.contains("fa-caret-right")){
                    expand(e.target);
                } else if (e.target.classList.contains("fa-minus")) {
                    hide(colIndex[colIndex.length - 1], this.firstElementChild);
                    e.target.classList.remove("fa-minus")
                    e.target.classList.add("fa-plus")
                } else {
                    show(colIndex[colIndex.length - 1], this.firstElementChild);
                    e.target.classList.remove("fa-plus")
                    e.target.classList.add("fa-minus")
                }

            });
        });
    }

    const collapse = (target) => {
        target.parentElement.setAttribute('hidden', true);
        target.parentElement.nextElementSibling.removeAttribute('hidden');
        collapseSubchapters(target.getAttribute("data-chapterid"));
    }

    const collapseSubchapters = (chapterid) => {

        //Hide headers        
        Selectors.chaptersMap.forEach((ch) => {
            if (ch.chapterid == chapterid) {
                ch.subchapters.forEach(sch => {

                    let columnIndex = document.getElementById(sch).closest('th').cellIndex;

                    if (document.getElementById(sch).closest('th').classList.contains(Selectors.expand)) {
                        document.getElementById(sch).closest('th').classList.remove(Selectors.expand)
                    }
                    document.getElementById(sch).closest('th').classList.add(Selectors.collapse);
                    collapseColumn(columnIndex);
                })
            }
        });

    }

    const collapseColumn = (colIndex) => {
        const t = document.querySelector("#graphcontributors tbody");;
        if (t) {
            Array.from(t.rows).forEach((tr) => {

                if ((tr.cells[colIndex]).classList.contains(Selectors.expand)) {
                    (tr.cells[colIndex]).classList.remove(Selectors.expand)
                }
                (tr.cells[colIndex]).classList.add(Selectors.collapse);

            }, colIndex);
        }
    }

    const expand = (target) => {
        target.parentElement.setAttribute('hidden', true);
        target.parentElement.previousElementSibling.removeAttribute('hidden');
        expandSubchapters(target.getAttribute("data-chapterid"));

    }

    const expandSubchapters = (chapterid) => {
        Selectors.chaptersMap.forEach((ch) => {
            if (ch.chapterid == chapterid) {
                ch.subchapters.forEach(sch => {

                    let columnIndex = document.getElementById(sch).closest('th').cellIndex;
                    document.getElementById(sch).closest('th').classList.remove(Selectors.collapse)

                    expandColumn(columnIndex);
                })
            }
        });
    }

    const expandColumn = (colIndex) => {
        const t = document.querySelector("#graphcontributors tbody");;
        if (t) {
            Array.from(t.rows).forEach((tr) => {

                (tr.cells[colIndex]).classList.remove(Selectors.collapse);

            }, colIndex);
        }
    }

    const hide = (colIndex, chaptertitle) => {
        const t = document.querySelector("#graphcontributors tbody");
        if (t) {
          
            Array.from(t.rows).forEach((tr) => {
                jQuery(tr.cells[colIndex]).children().hide();
                
            }, colIndex);

            jQuery(chaptertitle).hide();
        }
    }

    const show = (colIndex, chaptertitle) => {
        const t = document.querySelector("#graphcontributors tbody");;
        if (t) {
           
            Array.from(t.rows).forEach((tr) => {
                jQuery(tr.cells[colIndex]).children().show();
                

            }, colIndex);

            jQuery(chaptertitle).show();
        }
    }

    initListeners();
};