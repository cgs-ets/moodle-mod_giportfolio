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
global $CFG, $DB, $PAGE, $OUTPUT, $SITE, $USER, $SESSION;
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/comment/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$bid = optional_param('b', 0, PARAM_INT); // Giportfolio id.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.
$edit = optional_param('edit', -1, PARAM_BOOL); // Edit mode.
$useredit = optional_param('useredit', 0, PARAM_BOOL); // Edit mode.
$showshared = optional_param('showshared', null, PARAM_BOOL);
$mentor = optional_param('mentor', 0, PARAM_INT); // Mentor ID
$mentee = optional_param('mentee', 0, PARAM_INT);
$contribute = optional_param('cont', 'no', PARAM_RAW); // When teacher is contributing.

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

$allowedit = has_capability('mod/giportfolio:edit', $context);
$viewhidden = has_capability('mod/giportfolio:viewhiddenchapters', $context);
$allowcontribute = has_capability('mod/giportfolio:submitportfolio', $context) || $mentor;
$cangrade = has_capability('mod/giportfolio:gradegiportfolios', $context); // Allow a teacher to make a contrib on behalf of a student.

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

if ($showshared === null) {
    $showshared = false;
    if (isset($SESSION->giportfolio_show_shared)) {
        $showshared = $SESSION->giportfolio_show_shared;
    }
} else {
    $SESSION->giportfolio_show_shared = $showshared;
}

// Read chapters.
$chapters = giportfolio_preload_chapters($giportfolio);
// SYNERGY - add fake user chapters.
$additionalchapters = $mentee != 0 ? giportfolio_preload_userchapters($giportfolio, $mentee) : giportfolio_preload_userchapters($giportfolio);
if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}

// Get the alias given to student role. CGS
$alias = get_student_alias($course);

// SYNERGY.
if ($allowedit and !$chapters) {
    redirect('edit.php?cmid=' . $cm->id); // No chapters - add new one.
}

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
// SYNERGY.

// Display the Mentee info to make it clear which portfolio is the teacher or mentor contributing to. CGS customisation.
if ($mentee != 0) {
    giportfolio_adduser_fake_block($mentee, $giportfolio, $cm, $course->id, $mentor);
}

if (!$chapterid) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

if (!$chapter = $DB->get_record('giportfolio_chapters', array('id' => $chapterid, 'giportfolioid' => $giportfolio->id))) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}

$isuserchapter = (bool) $chapter->userid || $mentor != 0 || $chapter->userid == $USER->id;

if ($isuserchapter && !$allowcontribute  && !$cangrade) {
    throw new moodle_exception('notyourchapter', 'mod_giportfolio');
}

// Chapter is hidden for students.
if ($chapter->hidden and !$viewhidden) {
    print_error('errorchapter', 'mod_giportfolio', new moodle_url('/course/viewgiportfolio.php', array('id' => $course->id)));
}
$params = array('id' => $id, 'chapterid' => $chapterid, 'mentor' => $mentor, 'mentee' => $mentee, 'cont' => $contribute);

$PAGE->set_url('/mod/giportfolio/viewgiportfolio.php', $params);


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
$PAGE->add_body_class('mod_giportfolio');
$PAGE->set_heading(format_string($course->fullname));

// Synergy add $useredit.
giportfolio_add_fake_block($chapters, $chapter, $giportfolio, $cm, $edit, $useredit, $mentor, $mentee, $contribute);

// Prepare chapter navigation icons.
$previd = null;
$nextid = null;
$last = null;
foreach ($chapters as $ch) {
    if (!$edit and $ch->hidden) {
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
$mentorid = '&amp;mentor=' . $mentor;
$menteeid = '&amp;mentee=' . $mentee;
$contribute  = '&amp;cont=' . $contribute;
if ($previd) {
    $chnavigation .= '<a title="' . get_string('navprev', 'giportfolio') . '" href="viewgiportfolio.php?id=' . $cm->id .
        '&amp;chapterid=' . $previd . $mentorid . $menteeid . $contribute . '">
        <img src="' . $OUTPUT->image_url('nav_prev', 'mod_giportfolio') . '" class="bigicon" alt="' .
        get_string('navprev', 'giportfolio') . $menteeid . '"/></a>';
} else {
    $chnavigation .= '<img src="' . $OUTPUT->image_url('nav_prev_dis', 'mod_giportfolio') . '" class="bigicon" alt="" />';
}
if ($nextid) {
    $chnavigation .= '<a title="' . get_string('navnext', 'giportfolio') . '" href="viewgiportfolio.php?id=' . $cm->id .
        '&amp;chapterid=' . $nextid . $mentorid . $menteeid .$contribute .'">
        <img src="' . $OUTPUT->image_url('nav_next', 'mod_giportfolio') . '" class="bigicon" alt="' .
        get_string('navnext', 'giportfolio') . '" /></a>';
} else {
    $sec = '';
    if ($section = $DB->get_record('course_sections', array('id' => $cm->section))) {
        $sec = $section->section;
    }
    if ($course->id == $SITE->id) {
        $returnurl = "$CFG->wwwroot/";
    } else {
        $returnurl = "$CFG->wwwroot/course/view.php?id=$course->id#section-$sec";
    }
    $chnavigation .= '<a title="' . get_string('navexit', 'giportfolio') . '" href="' . $returnurl . '">
    <img src="' . $OUTPUT->image_url('nav_exit', 'mod_giportfolio') . '" class="bigicon" alt="' .
        get_string('navexit', 'giportfolio') . '" /></a>';

    // We are cheating a bit here, viewing the last page means user has viewed the whole giportfolio.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

// Add extra links.
$extralinks = '';
if (has_capability('giportfoliotool/print:print', $context)) {
    // Print links.
    $printallurl = new moodle_url('/mod/giportfolio/tool/print/index.php', array('id' => $cm->id));
    $extralinks .= html_writer::link($printallurl, get_string('printgiportfolio', 'giportfoliotool_print'));
    $extralinks .= html_writer::empty_tag('br');
    $printchapterurl = new moodle_url('/mod/giportfolio/tool/print/index.php',
        array('id' => $cm->id, 'chapterid' => $chapter->id));
    $extralinks .= html_writer::link($printchapterurl, get_string('printchapter', 'giportfoliotool_print'));
    $extralinks .= html_writer::empty_tag('br');
}
if (has_capability('mod/giportfolio:viewgiportfolios', $context)) {
    // Grading link.
    $url = new moodle_url('/mod/giportfolio/submissions.php', array('id' => $cm->id));
    $extralinks .= html_writer::link($url, get_string('studentgiportfolio', 'mod_giportfolio', $alias));
    $extralinks .= html_writer::empty_tag('br');
}
$url = new moodle_url('/mod/giportfolio/tool/export/zipgiportfolio.php', array('id' => $cm->id));
$extralinks .= html_writer::link($url, get_string('exportzip', 'mod_giportfolio'));
$extralinks = html_writer::div($extralinks, 'mod_giportfolio-extralinks');


// Giportfolio display HTML code.

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

// Upper nav.
echo '<div class="navtop">' . $chnavigation . '</div>';
echo $extralinks;

// Chapter itself.
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

// SYNERGY.
global $USER;
$pixpath = "$CFG->wwwroot/pix";

// Parent view of own child's activity functionality. CGS
$userid = $mentee == 0 ? $USER->id : $mentee;
$menteesmentorsid = giportfolio_get_mentees_mentor($userid);
$ids = ($mentee == 0) ? $userid : ((!empty($menteesmentorsid)) ? $menteesmentorsid . ',' . $userid : $userid);
$contriblist = giportfolio_get_user_contributions($chapter->id, $chapter->giportfolioid, $ids, $showshared);

if (count($contriblist) > 0) {
    giportfolio_set_mentor_info($contriblist, $userid);
}

$chaptertext = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $context->id, 'mod_giportfolio', 'chapter', $chapter->id);

$templatecontext->intro = $chaptertext;

echo $OUTPUT->render_from_template('mod_giportfolio/show_activity_description', $templatecontext); // Show/hide instruction button.

echo $OUTPUT->box_start('giportfolio_actions');

if (!$allowedit || $cangrade && $mentee != 0) {
    $params = array('id' => $cm->id, 'chapterid' => $chapter->id, 'mentor' => $mentor, 'mentee' => $mentee);
    if (!empty($contribution)) {
        $params['cont'] = $contribute;
    }
    $addurl = new moodle_url('/mod/giportfolio/editcontribution.php', $params);

    echo $OUTPUT->single_button($addurl, get_string('addcontrib', 'mod_giportfolio'), 'GET');
}



$otherusers = array();

if ($giportfolio->peersharing && $showshared) {
    $userids = array();
    foreach ($contriblist as $contrib) {
        if ($contrib->userid != $userid) {
            $userids[$contrib->userid] = $contrib->userid;
        }
    }
    if ($userids) {
        $namefields = get_all_user_name_fields(true);
        $users = $DB->get_records_list('user', 'id', $userids, '', 'id,' . $namefields);
        foreach ($users as $user) {
            $fullname = fullname($user);
            $profile = new moodle_url('/user/view.php', array('id' => $user->id));
            $otherusers[$user->id] = html_writer::link($profile, $fullname);
        }
    } else {
        echo html_writer::tag('p', get_string('noshared', 'mod_giportfolio', $alias));
    }
}

if (!$isuserchapter && $giportfolio->peersharing) {
    // If this is not a user chapter, display a button to show/hide other users' shared contributions,
    // as long as peersharing is enabled.
    if ($showshared) {
        $hidesharedurl = new moodle_url($PAGE->url, array('showshared' => 0, 'mentee' => $mentee));
        echo $OUTPUT->single_button($hidesharedurl, get_string('hideshared', 'mod_giportfolio', $alias), 'GET');
    } else {
        $showsharedurl = new moodle_url($PAGE->url, array('showshared' => 1, 'mentee' => $mentee));
        echo $OUTPUT->single_button($showsharedurl, get_string('showshared', 'mod_giportfolio', $alias), 'GET');
    }
}
echo $OUTPUT->box_end(); // giportfolio_actions
// Output the 'class plan' content.
if ($giportfolio->klassenbuchtrainer && giportfolio_include_klassenbuchtrainer()) {
    echo $OUTPUT->box_start('giportfolio_klassenbuchtrainer');
    echo klassenbuchtool_lernschritte_get_subcontent($chapter->id, $context, 'giportfolio');
    echo $OUTPUT->box_end(); // giportfolio_klassenbuchtrainer
}

if ($contriblist) {
    echo $OUTPUT->box_start('giportfolio_contributions');

    $contribution_buffer = '';
    $contribution_outline = '';
    if ($giportfolio->displayoutline) {
        $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/mod/giportfolio/outline.js'));
        $contribution_outline = '<p class="giportfolio_outline">' . get_string('outline', 'mod_giportfolio')
            . ' <span id="toggleoutline" class="toggleoutline">[ '
            . '<span id="togglehide">' . get_string('outline_hide', 'mod_giportfolio') . '</span>'
            . '<span id="toggleshow">' . get_string('outline_show', 'mod_giportfolio') . '</span> ]'
            . '</span></p><table id="giportfolio_outline" class="contents">';
    }

    $contribution_count = 0;

    comment::init();
    $commentopts = (object) array(
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
    $disabledelbtn = $DB->get_field('giportfolio', 'disabledeletebtn', ['id' => $giportfolio->id]);
  
    foreach ($contriblist as $contrib) {
        $ismine = ($contrib->userid == $userid);

        if ($ismine) {
            $baseurl = new moodle_url(
                '/mod/giportfolio/editcontribution.php',
                array(
                    'id' => $cm->id, 'contributionid' => $contrib->id, 'chapterid' => $contrib->chapterid,
                    'mentee' => $userid, 'mentor' => $contrib->mentorid, 'teacher' => $contrib->teacherid
                )
            );

            $editurl = new moodle_url($baseurl);
            $editicon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
            $editicon = html_writer::link($editurl, $editicon);
            $delurl = new moodle_url($baseurl, array('action' => 'delete'));
            $delicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
            $delicon = html_writer::link($delurl, $delicon);

            // Check if the show hide option is available for students.
            if (giportfolio_hide_show_contribution($giportfolio->id) || has_capability('mod/giportfolio:addinstance', $context)) {
                
                if ($contrib->hidden) {
                    $showurl = new moodle_url($baseurl, array('action' => 'show', 'sesskey' => sesskey()));
                    $showicon = $OUTPUT->pix_icon('t/show', get_string('show', 'mod_giportfolio'));
                } else {
                    $showurl = new moodle_url($baseurl, array('action' => 'hide', 'sesskey' => sesskey()));
                    $showicon = $OUTPUT->pix_icon('t/hide', get_string('hide', 'mod_giportfolio'));
                }
            }

            $showicon = html_writer::link($showurl, $showicon);
            $shareicon = '';

            if (!$isuserchapter && $giportfolio->peersharing) { // Only for chapters without a userid and if peersharing is enabled.
                if ($contrib->shared) {
                    $shareurl = new moodle_url($baseurl, array('action' => 'unshare', 'sesskey' => sesskey()));
                    $shareicon = $OUTPUT->pix_icon('unshare', get_string('unshare', 'mod_giportfolio', $alias), 'mod_giportfolio');
                } else {
                    $shareurl = new moodle_url($baseurl, array('action' => 'share', 'sesskey' => sesskey()));
                    $shareicon = $OUTPUT->pix_icon('share', get_string('share', 'mod_giportfolio', $alias), 'mod_giportfolio');
                }
                $shareicon = html_writer::link($shareurl, $shareicon);
            }

            
            if (!$disabledelbtn ) {
                $actions = array($editicon, $delicon, $showicon, $shareicon);
            } 
            
            if ($disabledelbtn && giportfolio_count_contributions_comments($contrib->id) > 0) {
                $actions = array();
                $PAGE->requires->js(new moodle_url($CFG->wwwroot . '/mod/giportfolio/deletecomment.js'));
            }
            
           
            $userfullname = '';
        } else if ($giportfolio->peersharing) {
            $actions = array(); // No actions when viewing another user's contribution.
            $userfullname = $otherusers[$contrib->userid] . ': ';
        } else {
            // Do not show contribution if peersharing is disabled, even if the contribution was previously shared
            continue;
        }
        $cout = '';
        $cout .= $userfullname . '<strong>' . format_string($contrib->title) . '</strong>  ' . implode(' ', $actions) . '<br>';
        $cout .= date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timecreated);
        if ($contrib->timecreated !== $contrib->timemodified) {
            $cout .= '<br/><i>' . get_string('lastmodified', 'mod_giportfolio') . date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timemodified) . '</i>';
        }
        $cout .= '<br/><br/>';

        $cout = html_writer::tag('contribheader', $cout);
        $contribtext = file_rewrite_pluginfile_urls($contrib->content, 'pluginfile.php', $context->id, 'mod_giportfolio',
            'contribution', $contrib->id);

        $cout .= html_writer::tag('contribtext', format_text($contribtext, $contrib->contentformat, array('noclean' => true, 'context' => $context)));

        $files = giportfolio_print_attachments($contrib, $cm, $type = null, $align = "right");
        if ($files) {
            $cout .= "<table border=\"0\" width=\"100%\" align=\"$align\"><tr><td align=\"$align\" nowrap=\"nowrap\">\n";
            $cout .= $files;
            $cout .= "</td></tr></table>\n";
            $cout .= '<br>';
        }

        if ($ismine) {
            $commentopts->itemid = $contrib->id;
            $commentbox = new comment($commentopts);
            $cout .= html_writer::tag('contribcomment', $commentbox->output());
            $cout .= '<br>';
        }

        $contribution_count++;

        $class = 'giportfolio-contribution';
        $class .= $ismine ? ' mine' : ' notmine';
        $contribution_buffer .= html_writer::tag('article', $cout, array('class' => $class, 'id' => 'contribution' . $contribution_count));

        if ($giportfolio->displayoutline) {

            $date_display = date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timecreated);
            if ($contrib->timecreated !== $contrib->timemodified) {
                $date_display .= '&nbsp;<span class="timemodified">&raquo;<span class="timemodified_details">'
                    . get_string('lastmodified', 'mod_giportfolio') . '<br/>'
                    . date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contrib->timemodified)
                    . '</span></span>';
            }

            $hidementortag = ($contrib->mentorid == 0) ? 'hidden' : '';
            $hideteachertag = ($contrib->teacherid == 0) ? 'hidden' : '';

            $contribution_outline .= html_writer::tag('tr',
                    '<td><a href="#contribution' . $contribution_count . '">' . format_string($contrib->title) . '</a></td>' .
                    '<td class="contribdate">' . $date_display . '</td>'.
                '<td class="badge badge-info"'.$hidementortag.' ><strong>'.format_string(get_string('mentorcontribution', 'mod_giportfolio')).'</td>'.
                    '<td class="badge badge-success"'.$hideteachertag.' ><strong>'.format_string(get_string('teachercontribution', 'mod_giportfolio')).'</td>',
                    array('class' => ($ismine ? 'mine' : 'notmine'))
            );
        }
    }

    if ($giportfolio->displayoutline) {
        echo $contribution_outline . '</table><br/>';
    }
    echo $contribution_buffer;
    echo $OUTPUT->box_end();
}

// SYNERGY.
echo $OUTPUT->box_end(); // giportfolio_content
echo '<br>';
// Lower navigation.
echo '<div class="navbottom">' . $chnavigation . '</div>';

echo $OUTPUT->footer();
