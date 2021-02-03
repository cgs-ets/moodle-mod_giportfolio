<?php
// This file is part of giportfolio module for Moodle - http://moodle.org/
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
 * Delete giportfolio user chapter
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
global $DB, $PAGE, $USER, $OUTPUT;

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = required_param('chapterid', PARAM_INT); // Chapter ID.
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$mentor = optional_param('mentor', 0, PARAM_INT); // Mentor ID
$mentee = optional_param('mentee', 0, PARAM_INT);

$cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);
$contribute = optional_param('cont', 'no', PARAM_RAW); // When teacher is contributing.

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);

$PAGE->set_url('/mod/giportfolio/deleteuserchapter.php', array('id' => $id, 'chapterid' => $chapterid));
$userid = ($mentor != 0 && $mentee!= 0 || has_capability('mod/giportfolio:gradegiportfolios', $context))? $mentee : $USER->id;

$chapter = $DB->get_record('giportfolio_chapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id,
    'userid' => $userid), '*', MUST_EXIST);

// Header and strings.
$PAGE->set_title(format_string($giportfolio->name));
$PAGE->add_body_class('mod_giportfolio');
$PAGE->set_heading(format_string($course->fullname));

// Form processing.
if ($confirm) { // The operation was confirmed.
    $fs = get_file_storage();

    if (!$chapter->subchapter) { // Delete all its subchapters if any.
        $params =  array('giportfolioid' => $giportfolio->id, 'userid' => $userid);
        $chapters = $DB->get_records('giportfolio_chapters', $params, 'pagenum', 'id, subchapter');
        $found = false;
        foreach ($chapters as $ch) {
            if ($ch->id == $chapter->id) {
                $found = true;
            } else if ($found and $ch->subchapter) {
                // Here I should delete contributions first.
                giportfolio_delete_user_contributions($ch->id, $userid, $giportfolio->id);
               $DB->delete_records('giportfolio_chapters', array('id' => $ch->id, 'userid' => $userid));
            } else if ($found) {
                break;
            }
        }
    }

    // Here I should delete contributions first.
    giportfolio_delete_user_contributions($chapter->id, $chapter->userid, $giportfolio->id);

    $DB->delete_records('giportfolio_chapters', array('id' => $chapter->id, 'userid' => $userid));

    giportfolio_preload_userchapters($giportfolio); // Fix structure.

    redirect('viewgiportfolio.php?id='.$cm->id.'&useredit=1&mentor='.$mentor.'&mentee='.$mentee);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

// The operation has not been confirmed yet so ask the user to do so.
if ($chapter->subchapter) {
    $strconfirm = get_string('confchapterdelete', 'mod_giportfolio');
} else {
    $strconfirm = get_string('confchapterdeleteall', 'mod_giportfolio');
}
echo '<br />';
$continue = new moodle_url('/mod/giportfolio/deleteuserchapter.php', array(
                                                                          'id' => $cm->id, 'chapterid' => $chapter->id,
                                                                          'confirm' => 1,
                                                                          'mentor' => $mentor,
                                                                          'mentee' => $mentee,
                                                                          'cont' => $contribute
                                                                     ));
$cancel = new moodle_url('/mod/giportfolio/viewgiportfolio.php', array('id' => $cm->id, 'chapterid' => $chapter->id,
    'mentor' => $mentor,
    'mentee' => $mentee,
    'cont' => $contribute));
echo $OUTPUT->confirm("<strong>$chapter->title</strong><p>$strconfirm</p>", $continue, $cancel);

echo $OUTPUT->footer();
