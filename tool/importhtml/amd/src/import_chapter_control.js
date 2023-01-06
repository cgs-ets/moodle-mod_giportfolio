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

import $ from 'jquery';
import Ajax from 'core/ajax';



export const init = ({

}) => {

    function createTree(chaptercollection) {
        Y.log("createTree...");

        Y.use('yui2-treeview', 'node-event-simulate', function (Y) {

            var treenodes = [];
            var html = '';

            chaptercollection.forEach(function (chapter, index) {

                html = "<p id ='" + chapter.id + "' class = 'ch-sub-title htmlnodelabel'>" + chapter.title + "</p>" +
                    "<a class = 'fa removech-from-tree' data-chapterid = '" + chapter.id + "'>&#xf00d</a>";

                var node = {
                    type: "html",
                    html: html,
                    contentStyle: 'icon-chapter'
                }

                if (chapter.subchapteraux) {
                    const subchapters = chapter.subchapteraux;

                    if (chapter.subchapteraux.length > 0) {
                        subchapters.forEach(function (sch) {
                            if (node.children == undefined) {
                                node.children = [];
                            }

                            html = `<p id = '${sch.id}' class = 'ch-sub-title is-subch htmlnodelabel' >${sch.title}</p>`;
                            node.children.push({
                                type: "html",
                                html: html,
                                contentStyle: 'icon-subchapter'
                            });
                        }, node);
                    }

                }
                treenodes.push(node);

            });

            var tree = new Y.YUI2.widget.TreeView('toc', treenodes);

            if (tree) {

                tree.render();

                tree.subscribe('clickEvent', function (event) {
                    var self = this; // Keep the reference of the tree.

                    const chapterid = document.getElementById(event.node.contentElId).firstChild.getAttribute('id');
                    const isSubchapter = document.getElementById(event.node.contentElId).firstChild.classList.contains('is-subch');
                    const data = {
                        chapterid: chapterid,
                        isSubchapter: isSubchapter
                    }

                    // add the edit ch-sub-title

                    $(document.getElementById(event.node.contentElId).firstChild).on("click", data, function () {
                        // add the edit ch-sub-title
                        var txt = $("#" + chapterid).text();
                        if (txt != '') {
                            if (data.isSubchapter) {
                                $("#" + chapterid).replaceWith(`<input type = 'text' class='mytxt ch-sub-title  is-subch htmlnodelabel' id='${chapterid}' maxlength='255' value ='${txt}'/>`);
                            } else {

                                $("#" + chapterid).replaceWith(`<input type = 'text' class='mytxt ch-sub-title htmlnodelabel' id='${chapterid}' maxlength='255' value ='${txt}'/>`);
                            }
                            $("#" + chapterid).val(txt);
                        }
                    });


                    $("input#" + chapterid).on("blur", data, function () {
                        var txt = $(this).val();
                        if ($(this).val() != '') {
                            if (data.isSubchapter) {
                                $(this).replaceWith(`<span class='mytxt ch-sub-title is-subch htmlnodelabel' id= '${chapterid}'>${txt}</span>`);
                            } else {

                                $(this).replaceWith(`<span class='mytxt ch-sub-title htmlnodelabel' id= '${chapterid}'>${txt}</span>`);
                            }
                        }

                        chaptercollection.forEach(function (chapter) {
                            if (!data.isSubchapter) {
                                if (chapter.id == data.chapterid.toString()) {
                                    chapter.title = $('#' + data.chapterid).html();
                                }

                            } else {
                                chapter.subchapteraux.forEach(function (subchapter) {
                                    if (subchapter.id == data.chapterid) {
                                        subchapter.title = $('#' + data.chapterid).html();
                                    }
                                }, data);
                            }

                        }, data);

                        document.querySelector('input[name="chapters"]').value = JSON.stringify(chaptercollection);
                    });

                    const eventData = {
                        treeNode: event.node,
                        tree: self,

                    }

                    $('.removech-from-tree').on('click', eventData, function (e) {

                        if (eventData.tree.removeNode(eventData.treeNode, true)) {

                            eventData.tree.render();

                            const id = e.target.getAttribute("data-chapterid");
                            // Remove chapters from JSON

                            const dataid = {
                                id: id,
                                toremove: [id]
                            }

                            chaptercollection = chaptercollection.filter(function (chapter) {

                                if (chapter.id == dataid.id) {
                                    dataid.toremove.push(...Object.values(chapter.subchapters));
                                } else {
                                    return chapter;
                                }
                            }, dataid);

                            let chapterids = JSON.parse(document.querySelector('input[name="chapterids"]').value);

                            chapterids = chapterids.filter(function (chid) {
                                if (!dataid.toremove.includes(chid)) {
                                    return id;
                                }
                            }, dataid);

                            let chapterandcoursemodule = JSON.parse(document.querySelector('input[name="chcm"]').value);

                            chapterandcoursemodule = chapterandcoursemodule.filter(function (cmod) {
                                if (!dataid.toremove.includes(cmod.chapterid)) {
                                    return cmod;
                                }
                            }, dataid);

                            document.querySelector('input[name="chapterids"]').value = JSON.stringify(chapterids);

                            document.querySelector('input[name="chapters"]').value = JSON.stringify(chaptercollection);

                            document.querySelector('input[name="chcm"]').value = JSON.stringify(chapterandcoursemodule);

                            // Check if there are no chapters in the tree to disable the save button.
                            if (Y.YUI2.widget.TreeView.getTree('toc').getNodeCount() == 0) {

                                document.querySelector('button.merge-chapters').setAttribute('disabled', true);
                                const toc = document.getElementById("toc");
                                while (toc.lastChild) { // Remove the children elements from the div.
                                    toc.removeChild(toc.lastChild);
                                }


                            }
                        }

                    });

                    return false;
                });

                tree.subscribe('enterKeyPressed', function (e) {
                    e.preventDefault();
                });

                tree.subscribe('focusChanged', function (e) {
                    e.preventDefault();
                });
                tree.subscribe('expand', function (e) {
                    e.preventDefault();
                });
            }

        });

    }

    const registerEventListeners = () => {

        $('#droptarget')
            // crucial for the 'drop' event to fire
            .on('dragover', function (e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = "copy"
                document.getElementById('droptarget').classList.add('dropeffect');
            })

            .on('drop', function (e) {
                // do something
                e.preventDefault();

                // Get the chapter details.
                const data = e.originalEvent.dataTransfer.getData("text/plain");
                const chapter = JSON.parse(data);
                let exists = false;
                // Check we are not trying to add the chapter to the same portfolio
                const giportfolioid = document.querySelector('input[name="giportfolioid"]').value;


                if (chapter.giportfolio == giportfolioid.toString()) { // Display error, user trying to add a chapter from the same portfolio.
                    document.getElementById('droptarget').classList.remove('dropeffect');
                    document.querySelector('.giportfolio-alert').closest('div').removeAttribute('hidden');

                    $(".gi-notallowed").fadeOut(2600, function () {
                        // Animation complete.
                    });

                } else {

                    var chaptercollection = [];
                    var chapterids = [];
                    var chapterandcoursemodule = [];

                    if (document.querySelector('input[name="chapters"]').value != '') {
                        chaptercollection = JSON.parse(document.querySelector('input[name="chapters"]').value);
                        chapterids = JSON.parse(document.querySelector('input[name="chapterids"]').value);
                        chapterandcoursemodule = JSON.parse(document.querySelector('input[name="chcm"]').value);
                        exists = checkduplicateChapter(chapter.id);
                    }

                    if (!exists) {
                        chaptercollection.push(chapter);
                        chapterids.push(chapter.id);

                        chapterandcoursemodule.push({
                            chapterid: chapter.id,
                            contextmodule: chapter.coursemodule
                        });

                        if (chapter.subchapteraux != undefined && chapter.subchapteraux.length > 0) {
                            const subchapterids = chapter.subchapteraux.map(({
                                id
                            }) => id);

                            for (let i = 0; i < subchapterids.length; i++) {
                                chapterandcoursemodule.push({
                                    chapterid: subchapterids[i],
                                    contextmodule: chapter.coursemodule
                                });
                            }
                            chapterids = chapterids.concat(subchapterids);
                        }
                    }

                    document.querySelector('input[name="chapters"]').value = JSON.stringify(chaptercollection);
                    document.querySelector('input[name="chapterids"]').value = JSON.stringify(chapterids);
                    document.querySelector('input[name="chcm"]').value = JSON.stringify(chapterandcoursemodule);
                    document.getElementById('droptarget').classList.remove('dropeffect');

                    if (document.querySelector('.merge-chapters').hasAttribute('disabled')) {
                        document.querySelector('.merge-chapters').removeAttribute('disabled');
                    }

                    //Make a tree
                    createTree(chaptercollection);
                }

                return false;
            });

        $('.merge-chapters').on('click', function (e) {
            saveChapters();
        });

        $('.merge-chapters-cancel').on('click', function (e) {
            window.location.href = document.querySelector('input[name="viewurl"]').value;
        });

        $('.gi-notallowed').on('click', function (e) {
            (e.target).closest('div').setAttribute('hidden', true);

        });

        $(".gi-import-success").on('click', function (e) {
            (e.target).closest('div').setAttribute('hidden', true);
        });

        $(".gi-import-fail").on('click', function (e) {
            (e.target).closest('div').setAttribute('hidden', true);
        })


    }

    const checkduplicateChapter = (chapterid) => {

        let chapters = JSON.parse(document.querySelector('input[name="chapters"]').value);
        chapters = chapters.filter(function (chapter) {

            if (chapter.id == chapterid) {
                return chapter;
            }

        }, chapterid);

        return (chapters.length > 0);

    }

    const saveChapters = () => {
        document.getElementById('giportfolio-import-chapter-overlay').removeAttribute('hidden');

        Ajax.call([{

            methodname: 'giportfoliotool_import_chapter',

            args: {
                chapterids: document.querySelector('input[name="chapterids"]').value,
                giportfolioid: document.querySelector('input[name="giportfolioid"]').value,
                cm: document.querySelector('input[name="cm"]').value,
                chapterandcoursemodule: document.querySelector('input[name="chcm"]').value,
                chaptersdetails: document.querySelector('input[name="chapters"]').value,

            },

            done: function (response) {

                document.getElementById('giportfolio-import-chapter-overlay').setAttribute('hidden', true);
                document.querySelector('.gi-import-success').closest('div').removeAttribute('hidden');

                $(".gi-success").fadeOut(2600, function () {
                    $(this).attr('hidden', true);
                    $(this).css('display', '');
                });

                document.getElementById('toc').removeChild(document.querySelector('.ygtvitem'));

                document.querySelector('input[name="chapters"]').value = '';
                document.querySelector('input[name="chapterids"]').value = '';
                document.querySelector('input[name="chcm"]').value = '';


                Y.use('yui2-treeview', 'node-event-simulate',
                    function (Y) {

                        let tree = Y.YUI2.widget.TreeView.getTree('toc')
                        tree.destroy();
                        tree = null;

                        const toc = document.getElementById("toc");
                        while (toc.lastChild) { // Remove the children elements from the div.
                            toc.removeChild(toc.lastChild);
                        }

                        document.querySelector('input[name="chapterids"]').value = '';
                        document.querySelector('input[name="chapters"]').value = '';
                        document.querySelector('input[name="chcm"]').value = '';
                    });

                    document.forms["merge-form"].submit(); // after doing the saving. Redirect to where I started
            },

            fail: function (reason) {
                document.querySelector('.gi-import-fail').closest('div').removeAttribute('hidden');
                document.getElementById('overlay').setAttribute('hidden', true);

                Y.use('yui2-treeview', 'node-event-simulate',
                    function (Y) {

                        let tree = Y.YUI2.widget.TreeView.getTree('toc');
                        tree.destroy();
                        tree = null;
                        document.querySelector('button.merge-chapters').setAttribute('disabled', true);

                        const toc = document.getElementById("toc");
                        while (toc.lastChild) { // Remove the children elements from the div.
                            toc.removeChild(toc.lastChild);
                        }

                        document.querySelector('input[name="chapterids"]').value = '';
                        document.querySelector('input[name="chapters"]').value = '';
                        document.querySelector('input[name="chcm"]').value = '';

                    });
            }

        }]);

    }

    registerEventListeners();
};