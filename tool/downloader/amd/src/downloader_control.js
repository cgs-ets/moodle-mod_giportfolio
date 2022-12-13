/* eslint-disable no-unused-vars */
/* eslint-disable require-jsdoc */
/* eslint-disable jsdoc/require-jsdoc */
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
 * @package    report
 * @subpackage ibassessmentreport
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/log"], function ($, Ajax, Log) {
    "use strict";

    function init() {
        Y.log("INIT");
        let userids = [];
        let items = [];
        let itemids = new Set();
        var control = new Controls(userids, items, itemids);
        control.main();
    }

    function Controls(userids, items, itemids) {
        let self = this;
        self.userids = userids;
        self.useritems = items;
        self.itemids = itemids;

    }

    /**
     * Run the controller.
     *
     */
    Controls.prototype.main = function () {
        let self = this;
        self.selectbyone();
        self.selectallaction();
    };

    Controls.prototype.selectbyone = function () {
        let self = this;
        let t = document.getElementById("contribution-downloader-tb");
        if (t) {
            //  Collect all the users ids
            Array.from(t.rows).forEach((tr) => {
                const checkbox = tr.cells[0].firstChild;
                checkbox.addEventListener(
                    "click",
                    self.selectbyonehandler.bind(self, this)
                );
            });
        }
    };

    Controls.prototype.selectallaction = function () {
        let self = this;
        let selectall = document.getElementById("selectall");
        selectall.addEventListener("click", self.selectallhandler.bind(self, this));
    };

    Controls.prototype.selectallhandler = function (s, e) {
        let t = document.getElementById("contribution-downloader-tb");
        const selectallcheckstatus = document.getElementById("selectall").checked;

        if (t) {
            Array.from(t.rows).forEach((tr) => {
                const checkbox = tr.cells[0].firstChild;
                const userid = checkbox.getAttribute("id");
                checkbox.checked = selectallcheckstatus;
                this.userChecked(userid, selectallcheckstatus);


            });
        }

    };

    Controls.prototype.selectbyonehandler = function (s, e) {
        let userid = e.target.id;
        this.userChecked(userid);
    };

    Controls.prototype.userChecked = function (userid, checkedstatus = null) {
        const checkbox = document.getElementById(userid);
        const username = checkbox.getAttribute('data-username');

        if (checkedstatus != null) {
            checkbox.checked = checkedstatus;
        }
        userid = userid.split("_");
        userid = userid[userid.length - 1];

        const contributions = document.getElementById(`contributions_${userid}`);
        const filesdata = contributions.getAttribute('data-chapter-contributions');
        const usersummary = {
            userid: userid,
            username: username,
            items: JSON.parse(filesdata)
        }

        if (checkbox.checked) {
            // Check that it doesnt exist  in the list. If its not then add
            if (!this.userids.includes(userid)) {
                this.useritems.push(usersummary);
                this.userids.push(userid);

            }
        } else { // Remove
            this.useritems = this.useritems.filter(usersummary => {
                if (usersummary.userid != userid) {
                    return usersummary;
                }
            });

            this.userids = this.userids.filter(uid => {
                if (uid != userid) {
                    return uid;
                }
            });
        }


        console.log(this.useritems);
        console.log(this.userids);

        let form = document.getElementById("download-contribution-form");
        let selectedusers = form.querySelector('input[name="selectedusers"]');
        let useritemids = form.querySelector('input[name="items"]');
        selectedusers.value = this.userids;
        useritemids.value = JSON.stringify(this.useritems);

        const submitBtn = document.querySelector('form#download-contribution-form > button');

        if (this.userids.length > 0) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }


    return {
        init: init
    };
});