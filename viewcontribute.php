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
 * giportfolio student view page
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
global $CFG, $DB, $OUTPUT, $PAGE, $SITE;
require_once($CFG->dirroot.'/mod/giportfolio/locallib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/comment/lib.php');

$userid = required_param('userid', PARAM_INT); // Student.
$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$bid = optional_param('b', 0, PARAM_INT); // Giportfolio id.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.
$contribute = optional_param('cont', 'no', PARAM_RAW);

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

$context = context_module::instance($cm->id);
require_capability('mod/giportfolio:view', $context);

$cansee = in_array($USER->id, giportfolio_user_mentor_of_student($userid));
$mentor = 0;

if ($cansee) {
    $mentor = $USER->id;
}

if ($mentor == 0 || !$cansee) {
    require_capability('mod/giportfolio:viewgiportfolios', $context);
}

$viewhidden = has_capability('mod/giportfolio:viewhiddenchapters', $context);
$cangrade = has_capability('mod/giportfolio:gradegiportfolios', $context);
$edit = 0;

// Read chapters.
$chapters = giportfolio_preload_chapters($giportfolio);

// SYNERGY - add fake user chapters.
$additionalchapters = giportfolio_preload_userchapters($giportfolio, $userid);
if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}
// SYNERGY.

// Check chapterid and read chapter data.
if ($chapterid == '0') { // Go to first chapter if no given.
    foreach ($chapters as $ch) {
        if ($edit) {
            $chapterid = $ch->id;
            break;
        }
        if (!$ch->hidden) {
            $chapterid = $ch->id;
            break;
        }
    }
}

if (!$chapterid) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

if (!$chapter = $DB->get_record('giportfolio_chapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id))) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}
if ($chapter->userid && $chapter->userid != $userid) {
    throw new moodle_exception('errorchapter', 'mod_giportfolio');
}

// Chapter is hidden for students.
if ($chapter->hidden && !$viewhidden) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewcontribute.php', array('id' => $course->id)));
}

// $params = array('id' => $id, 'chapterid' => $chapterid, 'userid' => $userid, 'cont' => $contribute);
$PAGE->set_url('/mod/giportfolio/viewcontribute.php', ['id' => $id]);

// Unset all page parameters.
unset($id);
unset($bid);
unset($chapterid);

// Security checks  END.

\mod_giportfolio\event\chapter_viewed::create_from_chapter($giportfolio, $context, $chapter);

// Read standard strings.
$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strtoc = get_string('toc', 'mod_giportfolio');

// Prepare header.
$PAGE->requires->jquery();
$PAGE->set_title(format_string($giportfolio->name));
$PAGE->set_heading(format_string($course->fullname));

giportfolio_adduser_fake_block($userid, $giportfolio, $cm, $course->id, $mentor);
giportfolio_add_fakeuser_block($chapters, $chapter, $giportfolio, $cm, $edit, $userid, $mentor);

// Prepare chapter navigation icons.
$previd = null;
$nextid = null;
$last = null;
foreach ($chapters as $ch) {
    if (!$edit && $ch->hidden) {
        continue;
    }
    if ($last == $chapter->id) {
        $nextid = $ch->id;
        break;
    }
    if ($ch->id != $chapter->id) {
        $previd = $ch->id;
    }
    $last = $ch->id;
}

$chnavigation = '';
// This data is common for both prev and next forms.
$data = [
    'cmid' => $cm->id,
    'mentor' => $mentor ,
    'userid' => $userid,
    'contribute' => $contribute,
    'viewcontribute' => 1
];

if ($mentor == 0) {
    unset($data['mentor']); // Teachers/admin.
}


if ($previd) {

    $data['chapterid'] = $previd;
    $data['prev'] = 1;
    $chnavigation .= $OUTPUT->render_from_template('mod_giportfolio/chapter_navigation_prev', $data);


} else {
    $data['inactive'] = 1;
    $chnavigation .= $OUTPUT->render_from_template('mod_giportfolio/chapter_navigation_prev', $data);
}
if ($nextid) {
    $data['chapterid'] = $nextid;
    $data['next'] = 1;
    $chnavigation .= $OUTPUT->render_from_template('mod_giportfolio/chapter_navigation_next', $data);

} else {
    $sec = '';
    if ($section = $DB->get_record('course_sections', array('id' => $cm->section))) {
        $sec = $section->section;
    }

    if ($course->id == $SITE->id) {
        $returnurl = "$CFG->wwwroot/";
    } else {
        $returnurl = "$CFG->wwwroot/mod/giportfolio/view.php?id=$cm->id";
    }

    $data['url'] = $returnurl;
    $data['id'] = $cm->id;

    $data['exit'] = 1;

    $chnavigation .= $OUTPUT->render_from_template('mod_giportfolio/chapter_navigation_next', $data);

    // We are cheating a bit here, viewing the last page means user has viewed the whole giportfolio.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

// Giportfolio display HTML code.

$realuser = $DB->get_record('user', array('id' => $userid));
$alias = get_student_alias($COURSE);
$PAGE->navbar->add(get_string('studentgiportfolio', 'mod_giportfolio', $alias),
                   new moodle_url('submissions.php?=', array('id' => $cm->id)));
$PAGE->navbar->add(fullname($realuser));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

// Upper nav.
echo '<div class="navtop">'.$chnavigation.'</div>';

// Chapter itself.
echo $OUTPUT->box_start('generalbox giportfolio_content');

// Add the anchor to show/hide the portfolio details. <span class="fa fa-caret-down badge-name"></span>.
$showhide = '<a type="button" class="show-hide-instructions" data-toggle="collapse" data-target="#collapseinstructions" aria-expanded="true"
aria-controls="collapseExample" title="Info"> <span class="fa fa-caret-down show-hide-details"></span> </a>';

if (!$giportfolio->customtitles) {
    $hidden = $chapter->hidden ? 'dimmed_text' : '';
    if (!$chapter->subchapter) {
        $currtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title '.$hidden.'">'.$currtitle.'</p>' . $showhide;
    } else {
        $currtitle = giportfolio_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $giportfolio, $context);
        $currsubtitle = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        echo '<p class="giportfolio_chapter_title '.$hidden.'">'.$currtitle.'<br />'.$currsubtitle. $showhide.'</p>';
    }
}

$contriblist = giportfolio_get_user_contributions($chapter->id, $chapter->giportfolioid, $userid);
$chaptertext = file_rewrite_pluginfile_urls(
    $chapter->content,
    'pluginfile.php',
    $context->id,
    'mod_giportfolio',
    'chapter',
    $chapter->id
);

$templatecontext = new \stdClass();
$templatecontext->intro = format_text($chaptertext, $chapter->contentformat, array('noclean' => true, 'context' => $context));
$templatecontext->menteementor = ($mentor != 0 || (isset($mentee) && $mentee == 0)) && !$cangrade;

echo $OUTPUT->render_from_template('mod_giportfolio/show_activity_description', $templatecontext); // Show/hide instruction button.

if ($contriblist) {
    echo $OUTPUT->box_start('giportfolio_contributions');

    $contributionbuffer = '';
    $contributionoutline = '';
    if ($giportfolio->displayoutline) {
        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/mod/giportfolio/outline.js'));
        $contributionoutline = '<p class="giportfolio_outline">' . get_string('outline', 'mod_giportfolio')
            . '<span id="toggleoutline" class="toggleoutline show-hide-details"> '
            . '<span id="togglehide" class="fa fa-caret-up" title= "Hide"></span>'
            . '<span id="toggleshow" class="fa fa-caret-down" title ="Show"></span> '
            . '</span></p><table id="giportfolio_outline" class="contents">';
    }

    $contributioncount = 0;

    comment::init();

    $commentopts = (object)array(
        'context' => $context,
        'component' => 'mod_giportfolio',
        'area' => 'giportfolio_contribution',
        'cm' => $cm,
        'course' => $course,
        'autostart' => true,
        'showcount' => true,
        'displaycancel' => true
    );

    $align = 'right';
    $actionsharing = [];
    $shareicon = '';
    foreach ($contriblist as $contrib) {
        $ismine = ($contrib->userid == $USER->id) || $mentor != 0;
        $baseurl = new moodle_url(
                '/mod/giportfolio/editcontribution.php',
                array(
                    'id' => $cm->id, 'contributionid' => $contrib->id, 'chapterid' => $contrib->chapterid,
                    'mentee' => $userid, 'mentor' => $contrib->mentorid, 'teacher' => $contrib->teacherid
                )
            );


        // Check if the show hide option is available for students.
        if (giportfolio_hide_show_contribution($giportfolio->id) || has_capability('mod/giportfolio:addinstance', $context)) {

            if ($contrib->hidden) {
                $showurl = new moodle_url($baseurl, array('action' => 'show', 'sesskey' => sesskey()));
                $showicon = $OUTPUT->pix_icon('t/show', get_string('show', 'mod_giportfolio'));
            } else {
                $showurl = new moodle_url($baseurl, array('action' => 'hide', 'sesskey' => sesskey()));
                $showicon = $OUTPUT->pix_icon('t/hide', get_string('hide', 'mod_giportfolio'));
                $actionsharing = array($shareicon);
            }
            $showicon = html_writer::link($showurl, $showicon);
        }

        $actions = array();
        if (!$contrib->hidden) {

            $editurl = new moodle_url($baseurl);
            $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
            $editicon = html_writer::link($editurl, $editicon);
            $delurl = new moodle_url($baseurl, array('action' => 'delete'));
            $delicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
            $delicon = html_writer::link($delurl, $delicon);

            if (!$giportfolio->disabledeletebtn ) {
                // Only allow to edit contributions done by the user.
                if ($contrib->mentorid == 0 && $contrib->teacherid == 0 && $contrib->userid != $USER->id) {
                    $actions = array($delicon, $showicon);
                } else {
                    $actions = array($editicon, $delicon, $showicon);
                }
            }

            $actions = array_merge($actions, $actionsharing);
            $cout = '';
            $hidementortag = ($contrib->mentorid == 0) ? 'hidden' : '';
            $hideteachertag = ($contrib->teacherid == 0) ? 'hidden' : '';
            $contribtitle = file_rewrite_pluginfile_urls($contrib->title, 'pluginfile.php', $context->id, 'mod_giportfolio', 'contribution', $contrib->id);
            $cout .= '<strong>'.$contribtitle.'</strong>'. implode(' ', $actions);
            $cout .= '<span class="badge badge-info contributor-tag"'.$hidementortag.'>'.format_string(get_string('mentorcontribution', 'mod_giportfolio')).'</span>';
            $cout .= '<span class="badge badge-success contributor-tag"'.$hideteachertag.'>'.format_string(get_string('teachercontribution', 'mod_giportfolio')).'</span> <br>';
            $cout .= date('l jS F Y'.($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timecreated);
            if ($contrib->timecreated !== $contrib->timemodified) {
                $cout .= '<br/><i>'.get_string('lastmodified', 'mod_giportfolio').date('l jS F Y'.($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timemodified).'</i>';
            }
            $cout .= '<br/><br/>';
            $cout = html_writer::tag('contribheader', $cout);


            // Print contribution body
            $contribtext = file_rewrite_pluginfile_urls($contrib->content, 'pluginfile.php', $context->id, 'mod_giportfolio',
                                                        'contribution', $contrib->id);
            $cout .= html_writer::tag('contribtext', format_text($contribtext, $contrib->contentformat, array('noclean' => true, 'context' => $context)));
            if (giportfolio_hide_show_contribution($giportfolio->id) || has_capability('mod/giportfolio:addinstance', $context)) {
                $showurl = new moodle_url($baseurl, array('action' => 'hide', 'sesskey' => sesskey()));
                $showicon = $OUTPUT->pix_icon('t/hide', get_string('hide', 'mod_giportfolio'));
                $cout .= html_writer::link($showurl, $shareicon);
            }

            $files = giportfolio_print_attachments($contrib, $cm, $type = null, $align = "right");
            if ($files) {
                $cout .= "<table border=\"0\" width=\"100%\" align=\"$align\"><tr><td align=\"$align\" nowrap=\"nowrap\">\n";
                $cout .= $files;
                $cout .= "</td></tr></table>\n";
                $cout .= '<br>';
            }

            $commentopts->itemid = $contrib->id;
            $commentbox = new comment($commentopts);
            $cout .= html_writer::tag('contribcomment', $commentbox->output(), array('id' => "$contrib->id".'_'."$chapter->id"));
            // Wrap contribution and make entry in the contents.
            $contributioncount++;
            $contributionbuffer .= html_writer::tag('article', $cout, array('class' => 'giportfolio-contribution', 'id' => 'contribution'.$contributioncount));

            if ($giportfolio->displayoutline) {
                $datedisplay = date('l jS F Y'.($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timecreated);
                if ($contrib->timecreated !== $contrib->timemodified) {
                    $datedisplay .= '&nbsp;<span class="timemodified">&raquo;<span class="timemodified_details">'
                        .get_string('lastmodified', 'mod_giportfolio').'<br/>'
                        .date('l jS F Y'.($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timemodified)
                    .'</span></span>';
                }

                $contributionoutline .= html_writer::tag('tr',
                    '<td><a href="#contribution'.$contributioncount.'">'.format_string($contrib->title).'</a></td>'.
                    '<td class="contribdate">'.$datedisplay.'</td>'.
                    '<td class="badge badge-info"'.$hidementortag.' ><strong>'.format_string(get_string('mentorcontribution', 'mod_giportfolio')).'</td>'.
                    '<td class="badge badge-success"'.$hideteachertag.' ><strong>'.format_string(get_string('teachercontribution', 'mod_giportfolio')).'</td>',
                    array('class' => ($ismine ? 'mine' : 'notmine'))
                );
            }


            if ($giportfolio->disabledeletebtn && giportfolio_count_contributions_comments($contrib->id) > 0) {
                $actions = array();
                $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/mod/giportfolio/deletecomment.js'));
            }

            if (empty(giportfolio_has_seen_contribution($contrib->id))) { // First time the user sees the contrib.
                giportfolio_follow_updates_entry($contrib);
            }

        }
    }

    if ($giportfolio->displayoutline) {
        echo $contributionoutline.'</table><br/><hr class ="outline-separator"><br>';
    }

    echo '<p class="giportfolio_outline" >'. get_string('contributions', 'giportfolio').'</p>';
    echo $contributionbuffer;
    echo $OUTPUT->box_end();
}

echo $OUTPUT->box_end();

// Lower navigation.
echo '<div class="navbottom">'.$chnavigation.'</div>';

echo $OUTPUT->footer();


