<?php
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
 * Downloader form
 *
 * @package    giportfoliotool_downloader
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class downloader_form extends moodleform {
    // Add elements to form.
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->addElement('hidden', 'chapterid', $this->_customdata['chapterid']);
        $mform->setType('id', PARAM_INT);
        $mform->setType('chapterid', PARAM_INT);

        $chapters = giportfoliotool_downloader_get_chapters($this->_customdata['giportfolioid']);
        $selectvalues = [];
        $firstchapterid = 0;

        foreach ($chapters as $chapter) {
            if ($firstchapterid == 0) {
                $firstchapterid = $chapter->id;
            }
            $selectvalues[$chapter->id] = $chapter->title;
        }

        $select = $mform->addElement('select', 'chapters', get_string('chapterselect', 'giportfoliotool_downloader'), $selectvalues);
        $select->setMultiple(true);
        $select->setSelected($firstchapterid);

        $this->add_action_buttons(true, 'Filter');

    }

}
