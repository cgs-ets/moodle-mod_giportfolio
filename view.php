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
 * giportfolio view page
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

global $CFG, $DB, $USER, $OUTPUT, $PAGE;
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->libdir . '/gradelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$bid = optional_param('b', 0, PARAM_INT); // Giportfolio id.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.
// Security checks START - teachers edit; students view.
if ($id) {
    $cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $giportfolio = $DB->get_record('giportfolio', array('id' => $bid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('giportfolio', $giportfolio->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_course_login($course, true, $cm);

$chapters = giportfolio_preload_chapters($giportfolio);

// SYNERGY - add fake user chapters.
$additionalchapters = giportfolio_preload_userchapters($giportfolio);
if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}
// SYNERGY.

$context = context_module::instance($cm->id);


require_capability('mod/giportfolio:view', $context);

// Parent view of own child's activity functionality
list($mentees, $mentor) = giportfolio_user_is_mentor($context, $USER);
$courseuserroles = enrol_get_course_users_roles($course->id);
$userswithaccesstoportofolio = giportfolio_users_with_access($courseuserroles, $course, $cm->id);
$mentorcancontribute = giportfolio_mentor_allowed_to_contribute($giportfolio->id);
$noneditingteachercancontribute = giportfolio_non_editing_teacher_allowed_to_contribute($giportfolio->id);
$allowedit = has_capability('mod/giportfolio:edit', $context);

$allowcontribute = has_capability('mod/giportfolio:submitportfolio', $context);

$allowreport = has_capability('report/outline:view', $context->get_course_context());
$allowview = has_capability('mod/giportfolio:view', $context);

if ($allowedit) {
    if ($edit != -1 and confirm_sesskey()) {
        $USER->editing = $edit;
    } else {
        if (isset($USER->editing)) {
            $edit = $USER->editing;
        } else {
            $edit = 0;
        }
    }
} else {
    $edit = 0;
}

if ($giportfolio->skipintro) {
    if (($allowcontribute && !$allowedit)) { // || $context->is_locked()
        // Redirect to the 'update contribution' page.
        redirect(new moodle_url('/mod/giportfolio/viewgiportfolio.php', array('id' => $cm->id)));
    }
}

// Read chapters.

$PAGE->set_url('/mod/giportfolio/view.php', array('id' => $id));
$PAGE->add_body_class('limitedwidth '); // Moodle 4 width
// Unset all page parameters.
unset($id);
unset($bid);

// Security checks  END.

\mod_giportfolio\event\course_module_viewed::create_from_giportfolio($giportfolio, $context)->trigger();

// Read standard strings.
$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strtoc = get_string('toc', 'mod_giportfolio');


// Prepare header.
$PAGE->set_title(format_string($giportfolio->name));
$PAGE->add_body_class('mod_giportfolio');
$PAGE->set_heading(format_string($course->fullname));

// Giportfolio display HTML code.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));
echo $OUTPUT->box_start('generalbox giportfolio_content');

$intro = file_rewrite_pluginfile_urls($giportfolio->intro, 'pluginfile.php', $context->id, 'mod_giportfolio', 'intro', '');
$templatecontext = new \stdClass();

$usercontribution = 0;
$showupdates = false;

// Teachers that are parents and can contribute
$teacherandmentor =  $allowedit && $mentor;
$viewdata = new \stdClass();
$viewdata->noneditingteacher = is_non_editing_teacher();
$viewdata->noneditingteachercancontribute = $noneditingteachercancontribute == 1;

$usersgiportfolios = giportfolio_get_giportfolios_number($giportfolio->id, $cm->id);
$viewdata->submittedportfolios = html_writer::link(new moodle_url('/mod/giportfolio/submissions.php', array('id' => $cm->id)), get_string('submitedporto', 'mod_giportfolio') . ' ' . $usersgiportfolios);

$menteebuttons = [];

if ($allowcontribute) {  //Student.
    $usercontribution = giportfolio_get_user_contribution_status($giportfolio->id, $USER->id);
    if ($usercontribution) {
        // Get user grade and feedback.
        $usergrade = grade_get_grades($course->id, 'mod', 'giportfolio', $giportfolio->id, $USER->id);
        if ($usergrade->items) {
            $gradeitemgrademax = $usergrade->items[0]->grademax;
            $userfinalgrade = $usergrade->items[0]->grades[$USER->id];
        }

        if ($usergrade->items && $userfinalgrade->grade) {
            $percentage = explode("/", $userfinalgrade->str_long_grade);
            $percentage[0] = floatval($percentage[0]);
            $percentage[1] = floatval($percentage[1]);
            $viewdata->usergraded = get_string('usergraded', 'mod_giportfolio') . number_format($userfinalgrade->grade, 2) .
                '  (' . $userfinalgrade->str_long_grade . ') - ' . round(($percentage[0] / $percentage[1]) * 100, 4) . '%';
            if ($userfinalgrade->feedback) {
                $viewdata->finalfeedback =  get_string('usergradefeedback', 'mod_giportfolio') . $userfinalgrade->feedback;
            }
        }
    }

    $viewdata->lastupdated = ($usercontribution) ? get_string('lastupdated', 'mod_giportfolio') . date('l jS \of F Y h:i:s A', $usercontribution) : '';
    $viewdata->chapternumber =  get_string('chapternumber', 'mod_giportfolio') . count($chapters);
} else if ($mentor) { // Parent

    $totalmenteesallowed = count(array_intersect_key($mentees, $userswithaccesstoportofolio));
    $totalmenteesenrolled = count(array_intersect_key($mentees, $courseuserroles));


    foreach ($mentees as $mentee) {
        if (!array_key_exists($mentee->id, $courseuserroles)) {
            continue;
        }
        if (!array_key_exists($mentee->id, $userswithaccesstoportofolio)) {
            if (($totalmenteesallowed == $totalmenteesenrolled) || $totalmenteesallowed == 0) {
                echo html_writer::start_tag('p', ['class' => 'alert alert-info giportfolio-restricted-access']);
                echo get_string('noaccessformentee', 'mod_giportfolio', ['name' => $mentee->firstname]);
                echo html_writer::end_tag('p');
            }
        } else {

            $user = \core_user::get_user($mentee->id);
            $userphoto = new \user_picture($user);
            $userphoto->size = 1; // Size f2.

            $ctx =  new stdClass();
            $ctx->photolarge = $userphoto->get_url($PAGE)->out(false);
            $ctx->cmid = $cm->id;
            $ctx->userid =  $mentee->id;

            $ctx->mentorid = $USER->id;
            $ctx->sesskey = $USER->sesskey;


            if (!$mentorcancontribute) {
                $ctx->inputname = 'userid';
                $ctx->allowcontribution = 'no';
                $ctx->action = 'viewcontribute.php';
                $ctx->textvalue =  get_string('viewmenteeportfolio', 'mod_giportfolio', ['name' => $mentee->firstname]);
            } else {
                $ctx->inputname = 'mentee';
                $ctx->allowcontribution = 'yes';
                $ctx->action = 'viewgiportfolio.php';
                $ctx->textvalue =  get_string('onbehalf', 'mod_giportfolio', ['name' => $mentee->firstname]);
            }
            $menteebuttons[] = $ctx;
        }
    }
}

$allowviewgiportfolios = has_capability('mod/giportfolio:viewgiportfolios', $context);
$viewdata->admin = is_siteadmin($USER->id);
$viewdata->student = $allowcontribute;
$viewdata->teacher = $allowedit || $allowviewgiportfolios;
$viewdata->teacherandmentor = $teacherandmentor;
$viewdata->mentor = $mentor;
$viewdata->playbutton = ($allowcontribute || $allowedit || $allowviewgiportfolios) && !$mentor;
$viewdata->playbuttonurl = new moodle_url('/mod/giportfolio/viewgiportfolio.php', array('id' => $cm->id, 'sesskey' => $USER->sesskey));
$viewdata->playparentbutton = $mentor;
$viewdata->skipintro = $giportfolio->skipintro;
$viewdata->intro = format_text($intro, $giportfolio->intro, array('noclean' => true, 'context' => $context));
$viewdata->chapternumbers = get_string('chapternumber', 'mod_giportfolio') . count($chapters);
$viewdata->contextlocked = $context->is_locked();
$viewdata->chapterishidden = giportfolio_all_chapters_hidden($giportfolio);
$viewdata->menteebuttons = $menteebuttons;

echo $OUTPUT->render_from_template('mod_giportfolio/view_portfolio_entry', $viewdata);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
