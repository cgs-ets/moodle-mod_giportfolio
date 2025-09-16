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
 * edit contribution
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $DB, $PAGE, $OUTPUT, $USER;
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');
require_once($CFG->dirroot . '/mod/giportfolio/editcontribution_form.php');

$cmid = required_param('id', PARAM_INT); // CMID.
$contributionid = optional_param('contributionid', 0, PARAM_INT);
$chapterid = required_param('chapterid', PARAM_INT); // Chapter ID.
$action = optional_param('action', null, PARAM_ALPHA);
$mentor = optional_param('mentor', 0, PARAM_INT); // Mentor ID
$mentee = optional_param('mentee', 0, PARAM_INT); // Mentee ID
$tid = optional_param('teacherid', 0, PARAM_INT); // Teacher ID

$cm = get_coursemodule_from_id('giportfolio', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);

$url = new moodle_url('/mod/giportfolio/editcontribution.php', array('id' => $cm->id, 'chapterid' => $chapterid ));

if ($action) {
    $url->param('action', $action);
}

if ($contributionid) {
    $url->param('contributionid', $contributionid);
}

$PAGE->set_url($url);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$cangrade = has_capability('mod/giportfolio:gradegiportfolios', $context); // Allow a teacher to make a contrib on behalf of a student.
$mentorcancontribute = giportfolio_mentor_allowed_to_contribute($giportfolio->id);

if (($mentor == 0 && !$mentorcancontribute) && !$cangrade) {
    require_capability('mod/giportfolio:submitportfolio', $context);
}

$maxfiles = 10; // Limit to 10 files for security.
$maxbytes = min($course->maxbytes, 50 * 1024 * 1024); // Max 50MB or course limit, whichever is smaller.

// Add instruction on the page code.
// Read chapters.
$chapters = giportfolio_preload_chapters($giportfolio);
// Add fake user chapters.
$additionalchapters = $mentee != 0 ? giportfolio_preload_userchapters($giportfolio, $mentee) : giportfolio_preload_userchapters($giportfolio);
if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}

$chapter = $DB->get_record('giportfolio_chapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id));
$cangrade = has_capability('mod/giportfolio:gradegiportfolios', $context); // Allow a teacher to make a contrib on behalf of a student.

if (!$cangrade) {
    if ($chapter->userid && $chapter->userid != $USER->id && $mentor == 0) {
        throw new moodle_exception('notyourchapter', 'mod_giportfolio');
    }
}

// Chapter is hidden for students.
if ($chapter->hidden) {
    require_capability('mod/giportfolio:viewhiddenchapters', $context);
}

giportfolio_add_fake_block($chapters, $chapter, $giportfolio, $cm, 0, 0, $mentor, $mentee); // Add TOC.

$editoroptions = array('noclean' => true, 'subdirs' => true, 'maxfiles' => 20, 'maxbytes' => $maxbytes, 'context' => $context);
$attachmentoptions = array('subdirs' => false, 'maxfiles' => $maxfiles, 'maxbytes' => $maxbytes);


if ($contributionid) {

    $contribution = $DB->get_record('giportfolio_contributions', array(
        'id' => $contributionid, 'chapterid' => $chapterid,
    ), '*', MUST_EXIST);

    $mentee = $contribution->userid;

    if (in_array($USER->id, giportfolio_user_mentor_of_student($mentee))) {
        $mentor = $USER->id;
    } else if ($USER->id == $mentee) {
        $mentee = 0; // Its either the student or the teacher
    }

    if (!isset($formdata)) {
        $formdata = new stdClass();
    }
    $formdata->mentor = $contribution->mentorid;
    $formdata->mentee = $contribution->userid;
    $formdata->teacherid = $contribution->teacherid;
    $formdata = clone ($contribution);
    $formdata = file_prepare_standard_editor(
        $formdata,
        'content',
        $editoroptions,
        $context,
        'mod_giportfolio',
        'contribution',
        $formdata->id
    );
    $formdata = file_prepare_standard_filemanager(
        $formdata,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_giportfolio',
        'attachment',
        $formdata->id
    );
    $formdata->contributionid = $formdata->id;
    $formdata->teacherid = $contribution->teacherid;

    if($giportfolio->numberhours) {
        $formdata->numhours = number_format($contribution->numhours, 2);
    }
} else {
    $formdata = new stdClass();
    $formdata->teacherid = ($mentor == 0 && $mentee != 0 && $USER->id != $mentee) ? $USER->id : 0; // Teacher on behalf of the student
}

$formdata->mentor = $mentor;
$formdata->mentee = $mentee;
$formdata->id = $cm->id;
$formdata->chapterid = $chapter->id;

// Header and strings.
$PAGE->set_title(format_string($giportfolio->name));
$PAGE->add_body_class('mod_giportfolio');
$PAGE->set_heading(format_string($course->fullname));

$params = array('id' => $cm->id, 'chapterid' => $chapter->id, /*'mentor' => $mentor,*/ 'mentee' => $mentee /*, 'cont' => $contribute*/);
$redir = new moodle_url('/mod/giportfolio/viewgiportfolio.php', $params);

// Handle delete / show / hide actions.
if ($action) {
    if (!$contribution) {
        print_error('invalidcontributionid', 'giportfolio');
    }

    if ($action == 'delete') {

        if (optional_param('confirm', false, PARAM_BOOL)) {
            require_sesskey();

            // Clean up files.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_giportfolio', 'contribution', $contribution->id);
            $fs->delete_area_files($context->id, 'mod_giportfolio', 'attachment', $contribution->id);

            // Delete the contribution.
            $DB->delete_records('giportfolio_contributions', array('id' => $contribution->id));

            giportfolio_automatic_grading($giportfolio, $contribution->userid);

            redirect($redir);
        } else {
            $title = format_string($contribution->title);
            $msg = get_string('confcontribdelete', 'mod_giportfolio');
            $msg = "<strong>{$title}</strong><p>$msg</p>";
            $continue = new moodle_url($PAGE->url, array('confirm' => 1, 'sesskey' => sesskey()));

            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($giportfolio->name));
            echo $OUTPUT->confirm($msg, $continue, $redir);
            echo $OUTPUT->footer();

            die();
        }
    } else if ($action == 'show') {
        require_sesskey();
        // Verify user owns this contribution or has grading capability.
        if ($contribution->userid != $USER->id && !$cangrade) {
            throw new moodle_exception('nopermissions', 'error');
        }
        if ($contribution->hidden) {
            $DB->set_field('giportfolio_contributions', 'hidden', 0, array('id' => $contribution->id));
        }
        redirect($redir);
    } else if ($action == 'hide') {
        require_sesskey();
        // Verify user owns this contribution or has grading capability.
        if ($contribution->userid != $USER->id && !$cangrade) {
            throw new moodle_exception('nopermissions', 'error');
        }
        if (!$contribution->hidden) {
            $DB->set_field('giportfolio_contributions', 'hidden', 1, array('id' => $contribution->id));
        }
        redirect($redir);
    } else if ($action == 'share') {
        require_sesskey();
        // Check if user is valid mentor of the contribution owner.
        $isvalidmentor = ($mentorcancontribute && in_array($USER->id, giportfolio_user_mentor_of_student($contribution->userid)));
        
        // Verify permissions: owner, teacher with grading capability, or valid mentor.
        if ($contribution->userid != $USER->id && !$cangrade && !$isvalidmentor) {
            throw new moodle_exception('nopermissions', 'error');
        }
        if (!$contribution->shared) {
            $DB->set_field('giportfolio_contributions', 'shared', 1, array('id' => $contribution->id));
        }
        redirect($redir);
    } else if ($action == 'unshare') {
        require_sesskey();
        // Check if user is valid mentor of the contribution owner.
        $isvalidmentor = ($mentorcancontribute && in_array($USER->id, giportfolio_user_mentor_of_student($contribution->userid)));
        
        // Verify permissions: owner, teacher with grading capability, or valid mentor.
        if ($contribution->userid != $USER->id && !$cangrade && !$isvalidmentor) {
            throw new moodle_exception('nopermissions', 'error');
        }
        if ($contribution->shared) {
            $DB->set_field('giportfolio_contributions', 'shared', 0, array('id' => $contribution->id));
        }
        redirect($redir);
    }
}

// Handle add new contribution / edit contribution.
$custom = array('editoroptions' => $editoroptions, 'attachmentoptions' => $attachmentoptions);
$mform = new mod_giportfolio_contribution_edit_form(null, $custom);
$mform->set_data($formdata);
$userid = $formdata->mentee == 0 ? $USER->id : $formdata->mentee;

if ($mform->is_cancelled()) {
    redirect($redir);
} else if ($data = $mform->get_data()) {

    if (!$contributionid) {
        // Create new contribution.
        $ins = (object)array(
            'chapterid' => $chapter->id,
            'giportfolioid' => $giportfolio->id,
            'pagenum' => 0,
            'subchapter' => 0,
            'title' => '', // Updated later.
            'content' => '', // Updated later.
            'contentformat' => FORMAT_HTML, // Updated later.
            'hidden' => 0, // Updated later.
            'timecreated' => time(),
            'timemodified' => 0, // Updated later.
            'userid' =>  $userid, //$formdata->mentee == 0 ? $USER->id : $formdata->mentee,
            'mentorid' => $formdata->mentor,
            'teacherid' => $formdata->teacherid
        );

        $contributionid = $DB->insert_record('giportfolio_contributions', $ins);
        $contribution = $DB->get_record('giportfolio_contributions', array('id' => $contributionid), '*', MUST_EXIST);
        $sendnotification = $giportfolio->notifyaddentry && $contribution->teacherid == 0;
        $sendnotificationtostudent = $giportfolio->notifyaddentryteacher && $contribution->teacherid != 0; // The teacher is contributing on behalf of the student

    }

    $data->id = $contributionid;
    $data->timemodified = time();

    $data = file_postupdate_standard_editor(
        $data,
        'content',
        $editoroptions,
        $context,
        'mod_giportfolio',
        'contribution',
        $contributionid
    );
    $data = file_postupdate_standard_filemanager(
        $data,
        'attachment',
        $attachmentoptions,
        $context,
        'mod_giportfolio',
        'attachment',
        $contributionid
    );

    $DB->update_record('giportfolio_contributions', $data);

    giportfolio_automatic_grading($giportfolio, $userid);

    if (isset($sendnotification) && $sendnotification) {

        giportfolio_add_entry_send_notification($userid, $cm, $giportfolio, $chapter);
    }

    if (isset($sendnotificationtostudent) && $sendnotificationtostudent) {
        giportfolio_add_entry_from_teacher_send_notification($userid, $cm, $giportfolio, $chapter);
    }

    redirect($redir);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

// Chapter itself instructions.
echo $OUTPUT->box_start('generalbox giportfolio_content');
if (!$giportfolio->customtitles) {
    $hidden = $chapter->hidden ? 'dimmed_text' : '';
    if (!$chapter->subchapter) {
        $currtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title ' . $hidden . '">' . $currtitle . '</p>';
    } else {
        $currtitle = giportfolio_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $giportfolio, $context);
        $currsubtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title ' . $hidden . '">' . $currtitle . '<br />' . $currsubtitle . '</p>';
    }
}

$chaptertext = file_rewrite_pluginfile_urls(
    $chapter->content,
    'pluginfile.php',
    $context->id,
    'mod_giportfolio',
    'chapter',
    $chapter->id
);

echo format_text($chaptertext, $chapter->contentformat, array('noclean' => true, 'context' => $context));

echo $OUTPUT->box_end();
$mform->display();
echo '<br />';
echo $OUTPUT->footer();
