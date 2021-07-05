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
 * giportfolio module local lib functions
 *
 * @package    mod_giportfolio
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/giportfolio/lib.php');
require_once($CFG->libdir . '/filelib.php');

define('PORTFOLIO_NUM_NONE', '0');
define('PORTFOLIO_NUM_NUMBERS', '1');
define('PORTFOLIO_NUM_BULLETS', '2');
define('PORTFOLIO_NUM_INDENTED', '3');

/**
 * Preload giportfolio chapters and fix toc structure if necessary.
 *
 * Returns array of chapters with standard 'pagenum', 'id, pagenum, subchapter, title, hidden'
 * and extra 'parent, number, subchapters, prev, next'.
 * Please note the content/text of chapters is not included.
 *
 * @param  stdClass $giportfolio
 * @return array of id=>chapter
 */
function giportfolio_preload_chapters($giportfolio)
{
    global $DB;
    $chapters = $DB->get_records(
        'giportfolio_chapters',
        array('giportfolioid' => $giportfolio->id, 'userid' => 0),
        'pagenum',
        'id, pagenum, subchapter, title, hidden, userid'
    );
    if (!$chapters) {
        return array();
    }

    $prev = null;
    $prevsub = null;

    $first = true;
    $hidesub = true;
    $parent = null;
    $pagenum = 0; // Chapter sort.
    $i = 0; // Main chapter num.
    $j = 0; // Subchapter num.
    foreach ($chapters as $id => $ch) {
        $oldch = clone ($ch);
        $pagenum++;
        $ch->pagenum = $pagenum;
        if ($first) {
            // Giportfolio can not start with a subchapter.
            $ch->subchapter = 0;
            $first = false;
        }
        if (!$ch->subchapter) {
            $ch->prev = $prev;
            $ch->next = null;
            if ($prev) {
                $chapters[$prev]->next = $ch->id;
            }
            if ($ch->hidden) {
                if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $i++;
                $ch->number = $i;
            }
            $j = 0;
            $prevsub = null;
            $hidesub = $ch->hidden;
            $parent = $ch->id;
            $ch->parent = null;
            $ch->subchpaters = array();
        } else {
            $ch->prev = $prevsub;
            $ch->next = null;
            if ($prevsub) {
                $chapters[$prevsub]->next = $ch->id;
            }
            $ch->parent = $parent;
            $ch->subchpaters = null;
            $chapters[$parent]->subchapters[$ch->id] = $ch->id;
            if ($hidesub) {
                // All subchapters in hidden chapter must be hidden too.
                $ch->hidden = 1;
            }
            if ($ch->hidden) {
                if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $j++;
                $ch->number = $j;
            }
        }
        if ($oldch->subchapter != $ch->subchapter or $oldch->pagenum != $ch->pagenum or $oldch->hidden != $ch->hidden) {
            // Update only if something changed.
            $DB->update_record('giportfolio_chapters', $ch);
        }
        $chapters[$id] = $ch;
    }

    return $chapters;
}

/**
 * Preload giportfolio chapters and fix toc structure if necessary.
 *
 * Returns array of chapters with standard 'pagenum', 'id, pagenum, subchapter, title, hidden'
 * and extra 'parent, number, subchapters, prev, next'.
 * Please note the content/text of chapters is not included.
 *
 * @param  stdClass $giportfolio
 * @param int $userid (optional) defaults to current user
 * @return array of id=>chapter
 */
function giportfolio_preload_userchapters($giportfolio, $userid = null)
{
    global $DB, $USER;
    if (!$userid) {
        $userid = $USER->id;
    }
    $chapters = $DB->get_records(
        'giportfolio_chapters',
        array('giportfolioid' => $giportfolio->id, 'userid' => $userid),
        'pagenum',
        'id, pagenum, subchapter, title, hidden, userid'
    );
    if (!$chapters) {
        return array();
    }

    $lastpage = giportfolio_get_last_chapter($giportfolio->id);

    $prev = null;
    $prevsub = null;

    $first = true;
    $hidesub = true;
    $parent = null;
    $pagenum = $lastpage->pagenum; // Chapter sort.
    $i = $lastpage->pagenum; // Main chapter num.
    $j = 0; // Subchapter num.
    foreach ($chapters as $id => $ch) {
        $oldch = clone ($ch);
        $pagenum++;
        $ch->pagenum = $pagenum;
        if ($first) {
            // Giportfolio can not start with a subchapter.
            $ch->subchapter = 0;
            $first = false;
        }
        if (!$ch->subchapter) {
            $ch->prev = $prev;
            $ch->next = null;
            if ($prev) {
                $chapters[$prev]->next = $ch->id;
            }
            if ($ch->hidden) {
                if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $i++;
                $ch->number = $i;
            }
            $j = 0;
            $prevsub = null;
            $hidesub = $ch->hidden;
            $parent = $ch->id;
            $ch->parent = null;
            $ch->subchpaters = array();
        } else {
            $ch->prev = $prevsub;
            $ch->next = null;
            if ($prevsub) {
                $chapters[$prevsub]->next = $ch->id;
            }
            $ch->parent = $parent;
            $ch->subchpaters = null;
            $chapters[$parent]->subchapters[$ch->id] = $ch->id;
            if ($hidesub) {
                // All subchapters in hidden chapter must be hidden too.
                $ch->hidden = 1;
            }
            if ($ch->hidden) {
                if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                    $ch->number = 'x';
                } else {
                    $ch->number = null;
                }
            } else {
                $j++;
                $ch->number = $j;
            }
        }
        if ($oldch->subchapter != $ch->subchapter or $oldch->pagenum != $ch->pagenum or $oldch->hidden != $ch->hidden) {
            // Update only if something changed.
            $DB->update_record('giportfolio_chapters', $ch);
        }
        $chapters[$id] = $ch;
    }

    return $chapters;
}

function giportfolio_get_chapter_title($chid, $chapters, $giportfolio, $context)
{

    $ch = $chapters[$chid];

    $title = trim(format_string($ch->title, true, array('context' => $context)));
    $numbers = array();
    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
        if ($ch->parent and $chapters[$ch->parent]->number) {
            $numbers[] = $chapters[$ch->parent]->number;
        }
        if ($ch->number) {

            $numbers[] = $ch->number;
        }
    }

    if ($numbers) {
        $title = implode('.', $numbers) . ' ' . $title;
    }

    return $title;
}

/**
 * General logging to table
 * @param string $str1
 * @param string $str2
 * @param int $level
 * @return void
 */
function giportfolio_log($str1, $str2, $level = 0)
{
    switch ($level) {
        case 1:
            echo '<tr><td><span class="dimmed_text">' . $str1 . '</span></td><td><span class="dimmed_text">' . $str2 . '</span></td></tr>';
            break;
        case 2:
            echo '<tr><td><span style="color: rgb(255, 0, 0);">' . $str1 . '</span></td><td><span style="color: rgb(255, 0, 0);">' .
                $str2 . '</span></td></tr>';
            break;
        default:
            echo '<tr><td>' . $str1 . '</class></td><td>' . $str2 . '</td></tr>';
            break;
    }
}

function giportfolio_add_fake_block($chapters, $chapter, $giportfolio, $cm, $edit, $userdit, $mentor = 0, $mentee = 0, $contribute = 'no')
{
    global $OUTPUT, $PAGE, $USER, $COURSE;

    $context = context_module::instance($cm->id);
    $context = $context->get_course_context();
    $allowreport = has_capability('report/outline:view', $context);
    $userid = ($mentor != 0 && $mentee != 0 || has_capability('mod/giportfolio:gradegiportfolios', $context)) ? $mentee : $USER->id;

    if ((giportfolio_get_collaborative_status($giportfolio) && !$edit) || $mentee != 0) {
        $toc = giportfolio_get_usertoc($chapters, $chapter, $giportfolio, $cm, $edit, $userid, $userdit, $mentor, $mentee, $contribute);
    } else {
        $toc = giportfolio_get_toc($chapters, $chapter, $giportfolio, $cm, $edit, $mentee);
    }

    if ($edit) {
        $toc .= '<div class="giportfolio_faq">';
        $toc .= $OUTPUT->help_icon('faq', 'mod_giportfolio', get_string('faq', 'mod_giportfolio'));
        $toc .= '</div>';
    }

    $bc = new block_contents();
    $bc->title = get_string('toc', 'mod_giportfolio');
    $bc->attributes['class'] = 'block';
    $bc->content = $toc;
    if ($allowreport && $giportfolio->myactivitylink) {
        $reportlink = new moodle_url(
            '/report/outline/user.php',
            array('id' => $USER->id, 'course' => $COURSE->id, 'mode' => 'outline')
        );
        $bc->content .= $OUTPUT->single_button($reportlink, get_string('courseoverview', 'mod_giportfolio'), 'get');
    }

    $regions = $PAGE->blocks->get_regions();
    $firstregion = reset($regions);
    $PAGE->blocks->add_fake_block($bc, $firstregion);

    // SYNERGY - add javascript to control subchapter collapsing.
    if (!$edit) {
        $jsmodule = array(
            'name' => 'mod_giportfolio_collapse',
            'fullpath' => new moodle_url('/mod/giportfolio/collapse.js'),
            'requires' => array('yui2-treeview')
        );

        $PAGE->requires->js_init_call('M.mod_giportfolio_collapse.init', array(), true, $jsmodule);
    }
    // SYNERGY - add javascript to control subchapter collapsing.
}

function giportfolio_add_fakeuser_block($chapters, $chapter, $giportfolio, $cm, $edit, $userid, $mentor = 0)
{
    global $OUTPUT, $PAGE;

    if (!$edit) {
        $toc = giportfolio_get_userviewtoc($chapters, $chapter, $giportfolio, $cm, $edit, $userid,  $mentor);
    } else {
        $toc = giportfolio_get_toc($chapters, $chapter, $giportfolio, $cm, $edit);
    }

    if ($edit) {
        $toc .= '<div class="giportfolio_faq">';
        $toc .= $OUTPUT->help_icon('faq', 'mod_giportfolio', get_string('faq', 'mod_giportfolio'));
        $toc .= '</div>';
    }

    $bc = new block_contents();
    $bc->title = get_string('usertoc', 'mod_giportfolio');
    $bc->attributes['class'] = 'block';
    $bc->content = $toc;

    $regions = $PAGE->blocks->get_regions();
    $firstregion = reset($regions);
    $PAGE->blocks->add_fake_block($bc, $firstregion);

    // SYNERGY - add javascript to control subchapter collapsing.
    if (!$edit) {
        $jsmodule = array(
            'name' => 'mod_giportfolio_collapse',
            'fullpath' => new moodle_url('/mod/giportfolio/collapse.js'),
            'requires' => array('yui2-treeview')
        );
        $PAGE->requires->js_init_call('M.mod_giportfolio_collapse.init', array(), true, $jsmodule);
    }
    // SYNERGY - add javascript to control subchapter collapsing.
}

/**
 * Generate toc structure
 *
 * @param array $chapters
 * @param stdClass $chapter
 * @param stdClass $giportfolio
 * @param stdClass $cm
 * @param bool $edit
 * @return string
 */
function giportfolio_get_toc($chapters, $chapter, $giportfolio, $cm, $edit, $mentee)
{
    global $USER, $OUTPUT;

    $toc = ''; // Representation of toc (HTML).
    $nch = 0; // Chapter number.
    $ns = 0; // Subchapter number.
    $first = 1;

    $context = context_module::instance($cm->id);

    // SYNERGY - add 'giportfolio-toc' ID.
    $tocid = ' id="giportfolio-toc" ';
    switch ($giportfolio->numbering) {
        case PORTFOLIO_NUM_NONE:
            $toc .= '<div class="giportfolio_toc_none" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_NUMBERS:
            $toc .= '<div class="giportfolio_toc_numbered" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_BULLETS:
            $toc .= '<div class="giportfolio_toc_bullets" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_INDENTED:
            $toc .= '<div class="giportfolio_toc_indented" ' . $tocid . '>';
            break;
    }

    // SYNERGY - add 'giportfolio-toc' ID.

    if ($edit) { // Teacher's TOC.
        $toc .= '<ul>';
        $i = 0;
        foreach ($chapters as $ch) {
            $i++;
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->subchapter) {
                $toc .= ($first) ? '<li>' : '</ul></li><li>';
                if (!$ch->hidden) {
                    $nch++;
                    $ns = 0;
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            } else {
                $toc .= ($first) ? '<li><ul><li>' : '<li>';
                if (!$ch->hidden) {
                    $ns++;
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x.x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            }

            if ($ch->id == $chapter->id) {
                $toc .= '<strong>' . $title . '</strong>';
            } else {
                $toc .= '<a title="' . s($title) . '" href="viewgiportfolio.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id . '&amp;mentee=' . $mentee .
                    '">' .
                    $title . '</a>';
            }
            $toc .= '&nbsp;&nbsp;';
            if ($i != 1) {
                $toc .= ' <a title="' . get_string('up') . '" href="move.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;up=1&amp;sesskey=' . $USER->sesskey . '">
                    <img src="' . $OUTPUT->image_url('t/up') . '" class="iconsmall" alt="' . get_string('up') . '" /></a>';
            }
            if ($i != count($chapters)) {
                $toc .= ' <a title="' . get_string('down') . '" href="move.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;up=0&amp;sesskey=' . $USER->sesskey . '">
                    <img src="' . $OUTPUT->image_url('t/down') . '" class="iconsmall" alt="' . get_string('down') . '" /></a>';
            }
            $toc .= ' <a title="' . get_string('edit') . '" href="edit.php?cmid=' . $cm->id . '&amp;id=' . $ch->id . '">
            <img src="' . $OUTPUT->image_url('t/edit') . '" class="iconsmall" alt="' . get_string('edit') . '" /></a>';
            $toc .= ' <a title="' . get_string('delete') . '" href="delete.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                '&amp;sesskey=' . $USER->sesskey . '">
                <img src="' . $OUTPUT->image_url('t/delete') . '" class="iconsmall" alt="' . get_string('delete') . '" /></a>';
            if ($ch->hidden) {
                $toc .= ' <a title="' . get_string('show') . '" href="show.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;sesskey=' . $USER->sesskey . '">
                    <img src="' . $OUTPUT->image_url('t/show') . '" class="iconsmall" alt="' . get_string('show') . '" /></a>';
            } else {
                $toc .= ' <a title="' . get_string('hide') . '" href="show.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;sesskey=' . $USER->sesskey . '">
                    <img src="' . $OUTPUT->image_url('t/hide') . '" class="iconsmall" alt="' . get_string('hide') . '" /></a>';
            }
            // Synergy  only if the giportfolio activity has not yet contributions.
            $toc .= ' <a title="' . get_string('addafter', 'mod_giportfolio') . '" href="edit.php?cmid=' . $cm->id .
                '&amp;pagenum=' . $ch->pagenum . '&amp;subchapter=' . $ch->subchapter . '">
                <img src="' . $OUTPUT->image_url('add', 'mod_giportfolio') . '" class="iconsmall" alt="' .
                get_string('addafter', 'mod_giportfolio') . '" /></a>';
            $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
            $first = 0;
        }
        $toc .= '</ul></li></ul>';
    } else { // Normal students view.
        $toc .= '<ul>';
        // SYNERGY - Find the open chapter.
        $currentch = 0;
        $opench = 0;
        foreach ($chapters as $ch) {
            if (!$currentch || !$ch->subchapter) {
                $currentch = $ch->id;
            }
            if ($ch->id == $chapter->id) {
                $opench = $currentch;
                break;
            }
        }
        // SYNERGY - Find the open chapter.
        foreach ($chapters as $ch) {
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->hidden) {
                if (!$ch->subchapter) {
                    $nch++;
                    $ns = 0;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    $li = '<li>';
                    if ($ch->id == $opench || !$giportfolio->collapsesubchapters) {
                        $li = '<li class="expanded">';
                    }
                    $toc .= ($first) ? $li : '</ul></li>' . $li;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    $ns++;
                    $toc .= ($first) ? '<li><ul><li>' : '<li>';
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                }
                if ($ch->id == $chapter->id) {
                    $toc .= '<strong>' . $title . '</strong>';
                } else {
                    $toc .= '<a title="' . s($title) . '" href="viewgiportfolio.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id . '">' .
                        $title . '</a>';
                }
                $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
                $first = 0;
            }
        }
        $toc .= '</ul></li></ul>';
    }

    $toc .= '</div>';

    $toc = str_replace('<ul></ul>', '', $toc); // Cleanup of invalid structures.

    return $toc;
}

/**
 * Generate user toc structure
 *
 * @param array $chapters
 * @param stdClass $chapter
 * @param stdClass $giportfolio
 * @param stdClass $cm
 * @param bool $edit
 * @param $userid
 * @param $useredit
 * @param $mentor
 * @param $mentee
 * @param $contribute
 * @return string
 */
function giportfolio_get_usertoc($chapters, $chapter, $giportfolio, $cm, $edit, $userid, $useredit, $mentor = 0, $mentee = 0, $contribute = 'no')
{
    global $USER, $OUTPUT;

    $toc = ''; // Representation of toc (HTML).
    $nch = 0; // Chapter number.
    $ns = 0; // Subchapter number.
    $first = 1;

    $context = context_module::instance($cm->id);

    // SYNERGY - add 'giportfolio-toc' ID.
    $tocid = ' id="giportfolio-toc" ';
    switch ($giportfolio->numbering) {
        case PORTFOLIO_NUM_NONE:
            $toc .= '<div class="giportfolio_toc_none" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_NUMBERS:
            $toc .= '<div class="giportfolio_toc_numbered" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_BULLETS:
            $toc .= '<div class="giportfolio_toc_bullets" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_INDENTED:
            $toc .= '<div class="giportfolio_toc_indented" ' . $tocid . '>';
            break;
    }
    // SYNERGY - add 'giportfolio-toc' ID.

    $allowuser = giportfolio_get_collaborative_status($giportfolio);

    if ($allowuser && $useredit) { // Edit students view.
        $toc .= '<ul>';
        $i = 0;
        // SYNERGY - Find the open chapter.
        $currentch = 0;
        $opench = 0;
        foreach ($chapters as $ch) {
            if (!$currentch || !$ch->subchapter) {
                $currentch = $ch->id;
            }
            if ($ch->id == $chapter->id) {
                $opench = $currentch;
                break;
            }
        }
        // SYNERGY - Find the open chapter.
        echo '<br/>';
        foreach ($chapters as $ch) {
            $i++;
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->subchapter) {
                $toc .= ($first) ? '<li>' : '</ul></li><li>';
                if (!$ch->hidden) {
                    $nch++;
                    $ns = 0;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    $li = '<li>';
                    if ($ch->id == $opench || !$giportfolio->collapsesubchapters) {
                        $li = '<li class="expanded">';
                    }
                    $toc .= ($first) ? $li : '</ul></li>' . $li;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            } else {
                $toc .= ($first) ? '<li><ul><li>' : '<li>';
                if (!$ch->hidden) {
                    $ns++;
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x.x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            }

            if ($ch->id == $chapter->id) {
                $toc .= '<strong>' . $title . '</strong>';
            } else {
                $toc .= '<a title="' . s($title) . '" href="viewgiportfolio.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;useredit=1' . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '">' . $title . '</a>';
            }
            $toc .= '&nbsp;&nbsp;';
            $userid = ($USER->id == $mentor) ? $mentee : $userid;

            if (giportfolio_check_user_chapter($ch, $userid)) {
                if ($i != 1) {
                    if (!giportfolio_get_first_userchapter($giportfolio->id, $ch->id, $userid)) {
                        $toc .= ' <a title="' . get_string('up') . '" href="moveuserchapter.php?id=' . $cm->id .
                            '&amp;chapterid=' . $ch->id . '&amp;up=1&amp;sesskey=' . $USER->sesskey . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '">
                            <img src="' . $OUTPUT->image_url('t/up') . '" class="iconsmall" alt="' . get_string('up') . '" /></a>';
                    }
                }
                if ($i != count($chapters)) {
                    $toc .= ' <a title="' . get_string('down') . '" href="moveuserchapter.php?id=' . $cm->id .
                        '&amp;chapterid=' . $ch->id . '&amp;up=0&amp;sesskey=' . $USER->sesskey . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '">
                        <img src="' . $OUTPUT->image_url('t/down') . '" class="iconsmall" alt="' . get_string('down') . '" /></a>';
                }
            }

            if (giportfolio_check_user_chapter($ch, $userid)) {
                $toc .= '<a title="' . get_string('edit')
                    . '" href="editstudent.php?cmid=' . $cm->id . '&amp;id='
                    . $ch->id . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '"> '
                    . '<img src="' . $OUTPUT->image_url('t/edit') . '" class="iconsmall" alt="' . get_string('edit') . '" /></a>';
            }

            if (giportfolio_check_user_chapter($ch, $userid)) {
                $toc .= ' <a title="' . get_string('delete') . '" href="deleteuserchapter.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id . '&amp;sesskey=' . $USER->sesskey . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '">
                    <img src="' . $OUTPUT->image_url('t/delete') . '" class="iconsmall" alt="' . get_string('delete') . '" /></a>';
            }

            if (
                giportfolio_check_user_chapter($ch, $userid) ||
                giportfolio_get_last_chapter($giportfolio->id, $ch->id)
            ) {

                $toc .= ' <a title="' . get_string('addafter', 'mod_giportfolio') . '" href="editstudent.php?cmid=' . $cm->id .
                    '&amp;pagenum=' . $ch->pagenum . '&amp;subchapter=' . $ch->subchapter . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '">
                    <img src="' . $OUTPUT->image_url('add', 'mod_giportfolio') . '" class="iconsmall" alt="' .
                    get_string('addafter', 'mod_giportfolio') . '" /></a>';
            }
            $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
            $first = 0;
        }
        $toc .= '</ul></li></ul>';
    } else {
        // Normal student nonediting view.
        $toc .= '<ul>';
        // SYNERGY - Find the open chapter.
        $currentch = 0;
        $opench = 0;
        foreach ($chapters as $ch) {
            if (!$currentch || !$ch->subchapter) {
                $currentch = $ch->id;
            }
            if ($ch->id == $chapter->id) {
                $opench = $currentch;
                break;
            }
        }
        // SYNERGY - Find the open chapter.
        foreach ($chapters as $ch) {
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->hidden) {
                if (!$ch->subchapter) {
                    $nch++;
                    $ns = 0;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    $li = '<li>';
                    if ($ch->id == $opench || !$giportfolio->collapsesubchapters) {
                        $li = '<li class="expanded">';
                    }
                    $toc .= ($first) ? $li : '</ul></li>' . $li;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    $ns++;
                    $toc .= ($first) ? '<li><ul><li>' : '<li>';
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                }
                if ($ch->id == $chapter->id) {
                    $toc .= '<strong>' . $title . '</strong>';
                } else {
                    $toc .= '<a title="' . s($title) . '" href="viewgiportfolio.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id
                        . '&amp;mentor=' . $mentor . '&amp;mentee=' . $mentee . '&amp;cont=' . $contribute . '">' .
                        $title . '</a>';
                }
                $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
                $first = 0;
            }
        }
        $toc .= '</ul></li></ul>';
    }

    $toc .= '</div>';

    $toc = str_replace('<ul></ul>', '', $toc); // Cleanup of invalid structures.

    return $toc;
}

function giportfolio_get_userviewtoc($chapters, $chapter, $giportfolio, $cm, $edit, $userid, $mentor = 0)
{
    $toc = ''; // Representation of toc (HTML).
    $nch = 0; // Chapter number.
    $ns = 0; // Subchapter number.
    $first = 1;

    $context = context_module::instance($cm->id);

    // SYNERGY - add 'giportfolio-toc' ID.
    $tocid = ' id="giportfolio-toc" ';
    switch ($giportfolio->numbering) {
        case PORTFOLIO_NUM_NONE:
            $toc .= '<div class="giportfolio_toc_none" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_NUMBERS:
            $toc .= '<div class="giportfolio_toc_numbered" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_BULLETS:
            $toc .= '<div class="giportfolio_toc_bullets" ' . $tocid . '>';
            break;
        case PORTFOLIO_NUM_INDENTED:
            $toc .= '<div class="giportfolio_toc_indented" ' . $tocid . '>';
            break;
    }
    // SYNERGY - add 'giportfolio-toc' ID.

    if ($tocid) { // Normal students view.
        $ismentor = '&amp;mentor=' . $mentor;
        $toc .= '<ul>';
        $i = 0;

        // SYNERGY - Find the open chapter.
        $currentch = 0;
        $opench = 0;
        foreach ($chapters as $ch) {
            if (!$currentch || !$ch->subchapter) {
                $currentch = $ch->id;
            }
            if ($ch->id == $chapter->id) {
                $opench = $currentch;
                break;
            }
        }
        foreach ($chapters as $ch) {
            $i++;
            $title = trim(format_string($ch->title, true, array('context' => $context)));
            if (!$ch->subchapter) {
                if (!$ch->hidden) {
                    $nch++;
                    $ns = 0;
                    // SYNERGY - Make sure the right subchapters are expanded by default.
                    $li = '<li>';
                    if ($ch->id == $opench || !$giportfolio->collapsesubchapters) {
                        $li = '<li class="expanded">';
                    }

                    $toc .= ($first) ? $li : '</ul></li>' . $li;

                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            } else {
                $toc .= ($first) ? '<li><ul><li>' : '<li>';
                if (!$ch->hidden) {
                    $ns++;
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "$nch.$ns $title";
                    }
                } else {
                    if ($giportfolio->numbering == PORTFOLIO_NUM_NUMBERS) {
                        $title = "x.x $title";
                    }
                    $title = '<span class="dimmed_text">' . $title . '</span>';
                }
            }

            if ($ch->id == $chapter->id) {
                $toc .= '<strong>' . $title . '</strong>';
            } else {
                $toc .= '<a title="' . s($title) . '" href="viewcontribute.php?id=' . $cm->id . '&amp;chapterid=' . $ch->id .
                    '&amp;userid=' . $userid . $ismentor . '">' . $title . '</a>';
            }
            $toc .= '&nbsp;&nbsp;';
            $toc .= (!$ch->subchapter) ? '<ul>' : '</li>';
            $first = 0;
        }
        $toc .= '</ul></li></ul>';
    }
    $toc .= '</div>';

    $toc = str_replace('<ul></ul>', '', $toc); // Cleanup of invalid structures.

    return $toc;
}

function giportfolio_get_collaborative_status($giportfolio)
{ // Check if the activity is allowing users to add chapters.
    return $giportfolio->participantadd;
}

function giportfolio_get_user_contributions($chapterid, $giportfolioid, $ids, $showshared = false)
{ // Return user contributions for a chapter-page.
    global $DB;

    $sharedsql = '';
    if ($showshared) {
        $sharedsql = ' OR shared = 1';
    }
    $sql = "SELECT * FROM {giportfolio_contributions}
            WHERE chapterid = :chapterid AND giportfolioid= :giportfolioid
            AND (userid  in ($ids) $sharedsql)
            ORDER BY timemodified DESC
            ";
    $params = array('giportfolioid' => $giportfolioid, 'chapterid' => $chapterid);

    return $DB->get_records_sql($sql, $params);
}

function giportfolio_set_mentor_info($contributions, $menteeid)
{
    $mentorids = giportfolio_get_mentees_mentor($menteeid);
    $mentorids = explode(',', $mentorids);
    foreach ($contributions as $contribution) {
        if (in_array($contribution->userid, $mentorids)) {
            $contribution->mentorid = $contribution->userid;
            $contribution->userid = $menteeid;
        }
    }
    return $contribution;
}

function giportfolio_get_user_default_chapter($giportfolioid, $userid) { // Part of Allow a teacher to make a contribution on behalf of a student.
    global $DB;

    $sql = "SELECT TOP(1) chapterid  FROM mdl_giportfolio_contributions
            WHERE  giportfolioid = {$giportfolioid}
           --LIMIT 1; ";

    return  $DB->get_record_sql($sql);
}


function giportfolio_get_user_chapters($giportfolioid, $userid)  { // Return user added chapters for a giportfolio.
    global $DB;

    $sql = "SELECT * FROM {giportfolio_chapters}
            WHERE giportfolioid = :giportfolioid AND userid = :userid
            ORDER BY pagenum ASC
            ";
    $params = array('giportfolioid' => $giportfolioid, 'userid' => $userid);

    $userchapters = $DB->get_records_sql($sql, $params);

    if ($userchapters) {
        return $userchapters;
    } else {
        return 0;
    }
}

function giportfolio_get_user_contribution_status($giportfolioid, $userid) { // Return (if exists) the last contribution date to a giportfolio for a user.
    
    global $DB;

    $params = array(
        'giportfolioid' => $giportfolioid,
        'userid' => $userid,
        'hidden' => 0,
    );
    $contribtime = $DB->get_field('giportfolio_contributions', 'MAX(timemodified)', $params);
    $chaptertime = $DB->get_field('giportfolio_chapters', 'MAX(timemodified)', $params);

    return (int)max($contribtime, $chaptertime);
}


function giportfolio_get_giportfolios_number($giportfolioid, $cmid) {
    // Return (if exists) the number of student giportfolios for each activity.
    global $DB;

    $context = context_module::instance($cmid);
    $userids = get_users_by_capability($context, 'mod/giportfolio:submitportfolio', 'u.id', '', '', '', '', '', false, true);
    if (empty($userids)) {
        return 0;
    }

    list($usql, $params) = $DB->get_in_or_equal(array_keys($userids), SQL_PARAMS_NAMED);
    $sql = "SELECT COUNT(DISTINCT submitted.userid)
              FROM (
                SELECT userid
                  FROM {giportfolio_contributions}
                 WHERE giportfolioid= :giportfolioid
                 GROUP BY userid
                 UNION
                SELECT userid
                  FROM {giportfolio_chapters}
                 WHERE giportfolioid= :giportfolioid2
                 GROUP BY userid
              ) AS submitted
             WHERE submitted.userid $usql
            ";
    $params['giportfolioid'] = $giportfolioid;
    $params['giportfolioid2'] = $giportfolioid;

    $giportfolionumber = $DB->count_records_sql($sql, $params);

    return $giportfolionumber;
}

function giportfolio_chapter_count_contributions($giportfolioid, $chapterid) {
    global $DB;

    $chapterids = array($chapterid);
    $chapters = $DB->get_records('giportfolio_chapters', array('giportfolioid' => $giportfolioid), 'pagenum', 'id, subchapter');
    $found = false;
    foreach ($chapters as $ch) {
        if ($ch->id == $chapterid) {
            $found = true;
            if ($ch->subchapter) {
                break; // This chapter is already a subchapter, so won't have subchapters of its own.
            }
        } else if ($found and $ch->subchapter) {
            $chapterids[] = $ch->id;
        } else if ($found) {
            break;
        }
    }

    list($csql, $params) = $DB->get_in_or_equal($chapterids, SQL_PARAMS_NAMED);
    $select = "giportfolioid = :giportfolioid AND chapterid $csql";
    $params['giportfolioid'] = $giportfolioid;

    return $DB->count_records_select('giportfolio_contributions', $select, $params);
}

function giportfolio_adduser_fake_block($userid, $giportfolio, $cm, $courseid, $mentor = 0)
{
    global $OUTPUT, $PAGE, $CFG, $DB;

    require_once($CFG->libdir . '/gradelib.php');

    $ufields = user_picture::fields('u');

    $select = "SELECT $ufields ";

    $sql = 'FROM {user} u ' . 'WHERE u.id=' . $userid;

    $user = $DB->get_record_sql($select . $sql);

    $picture = $OUTPUT->user_picture($user);

    $usercontribution = giportfolio_get_user_contribution_status($giportfolio->id, $userid);

    $lastupdated = '';
    $userfinalgrade = null;
    if ($usercontribution) {
        $lastupdated = date('l jS \of F Y ', $usercontribution);
        $usergrade = grade_get_grades($courseid, 'mod', 'giportfolio', $giportfolio->id, $userid);
        if ($usergrade->items && isset($usergrade->items[0]->grades[$userid])) {
            $userfinalgrade = $usergrade->items[0]->grades[$userid];
        }
    }

    $bc = new block_contents();
    $bc->title = get_string('giportfolioof', 'mod_giportfolio');
    $bc->attributes['class'] = 'block';
    $bc->content = '<strong>' . fullname($user, true) . '</strong>';
    $bc->content .= '<br/>';
    $bc->content .= $picture;
    $bc->content .= '<br/>';
    $bc->content .= '<strong>' . get_string('lastupdated', 'mod_giportfolio') . '</strong>';
    $bc->content .= '<br/>';
    $bc->content .= $lastupdated;

    if ($mentor == 0) {
        $hasgrade = ($userfinalgrade && (!is_null($userfinalgrade->grade) || $userfinalgrade->feedback));
        $gradelocked = ($userfinalgrade && $userfinalgrade->locked);

        $bc->content .= '<br/>';
        if ($hasgrade) {
            $bc->content .= '<strong>' . get_string('grade') . '</strong>';
            $bc->content .= '<br/>';
            $bc->content .= $userfinalgrade->grade . '  ';
        }
        if (!$gradelocked) {
            $gradeurl = new moodle_url('/mod/giportfolio/updategrade.php', array('id' => $cm->id, 'userid' => $userid));
            $strgrade = $hasgrade ? get_string('upgrade', 'mod_giportfolio') : get_string('insertgrade', 'mod_giportfolio');
            $bc->content .= html_writer::link($gradeurl, $strgrade);
        }
        if ($hasgrade) {
            if ($userfinalgrade->feedback) {
                $feedback = $userfinalgrade->feedback;
            } else {
                $feedback = '-';
            }
            $bc->content .= '<br/>';
            $bc->content .= '<strong>' . get_string('feedback') . '</strong>';
            $bc->content .= '<br/>';
            $bc->content .= $feedback;
        }
    }
    $bc->content .= '<br/>';

    $regions = $PAGE->blocks->get_regions();
    $firstregion = reset($regions);
    $PAGE->blocks->add_fake_block($bc, $firstregion);
}

function giportfolio_quick_update_grades($id, $menu, $currentgroup, $giportfolioid)
{
    // Update or insert grades from quick gradelib form.
    global $USER, $DB;
    $context = context_module::instance($id);
    $allportousers = get_users_by_capability(
        $context,
        'mod/giportfolio:view',
        'u.id,u.picture,u.firstname,u.lastname,u.idnumber',
        'u.firstname ASC',
        '',
        '',
        $currentgroup,
        '',
        false,
        true
    );
    $itemid = giportfolio_get_gradeitem($giportfolioid);

    foreach ($allportousers as $puser) {
        if (!empty($menu[$puser->id])) {
            if ($menu[$puser->id] == -1) {
                $menu[$puser->id] = 0;
            }
            $gradeid = giportfolio_get_usergrade_id($itemid, $puser->id);
            if ($gradeid) {
                $newgrade = new stdClass();
                $newgrade->id = $gradeid;
                $newgrade->itemid = $itemid;
                $newgrade->userid = $puser->id;
                $newgrade->usermodified = $USER->id;
                $newgrade->finalgrade = $menu[$puser->id];
                $newgrade->rawgrade = $menu[$puser->id];
                $newgrade->timemodified = time();
                $DB->update_record('grade_grades', $newgrade);
            } else {
                $insertgrade = new stdClass();
                $insertgrade->itemid = $itemid;
                $insertgrade->userid = $puser->id;
                $insertgrade->rawgrade = $menu[$puser->id];
                $insertgrade->rawgrademax = 100.00000;
                $insertgrade->rawgrademin = 0.00000;
                $insertgrade->usermodified = $USER->id;
                $insertgrade->finalgrade = $menu[$puser->id];
                $insertgrade->hidden = 0;
                $insertgrade->locked = 0;
                $insertgrade->locktime = 0;
                $insertgrade->exported = 0;
                $insertgrade->overridden = 0;
                $insertgrade->excluded = 0;
                $insertgrade->feedbackformat = 1;
                $insertgrade->informationformat = 0;

                $insertgrade->timecreated = time();
                $insertgrade->timemodified = time();

                $DB->insert_record('grade_grades', $insertgrade);
            }
        }
    }
}

function giportfolio_quick_update_feedback($id, $menu, $currentgroup, $giportfolioid)
{ // Update feedback from quick gradelib form.
    global $USER, $DB;
    $context = context_module::instance($id);
    $allportousers = get_users_by_capability(
        $context,
        'mod/giportfolio:view',
        'u.id,u.picture,u.firstname,u.lastname,u.idnumber',
        'u.firstname ASC',
        '',
        '',
        $currentgroup,
        '',
        false,
        true
    );
    $itemid = giportfolio_get_gradeitem($giportfolioid);

    foreach ($allportousers as $puser) {
        if (!empty($menu[$puser->id])) {
            $gradeid = giportfolio_get_usergrade_id($itemid, $puser->id);

            $newgrade = new stdClass();
            $newgrade->id = $gradeid;
            $newgrade->itemid = $itemid;
            $newgrade->userid = $puser->id;
            $newgrade->usermodified = $USER->id;
            $newgrade->feedback = $menu[$puser->id];
            $newgrade->timemodified = time();
            $DB->update_record('grade_grades', $newgrade);
        }
    }
}

function giportfolio_get_gradeitem($giportfolioid)
{ // Return grade item id for update.
    global $DB;
    $itemid = $DB->get_record_sql("SELECT p.id,p.course,p.name,gi.id as itemid
    FROM {giportfolio} p
    LEFT JOIN {grade_items} gi on (p.id=gi.iteminstance AND p.course=gi.courseid)
    WHERE p.id= ? AND gi.itemmodule= 'giportfolio'
    ", array($giportfolioid));
    if ($itemid) {
        return $itemid->itemid;
    } else {
        return 0;
    }
}

function giportfolio_get_usergrade_id($itemid, $userid)
{ // Return grade item id for update.
    global $CFG, $DB;
    $gradeid = $DB->get_record_sql("SELECT gg.id,gg.itemid,gg.userid
    FROM {$CFG->prefix}grade_grades gg
    WHERE gg.itemid= $itemid AND gg.userid=$userid
    ");

    if ($gradeid) {
        return $gradeid->id;
    } else {
        return 0;
    }
}

function giportfolio_get_last_chapter($giportfolioid, $chapterid = null)
{
    // Return the last chapter of a teacher defined giportfolio.
    global $DB;

    if ($chapterid) {
        $sql = "SELECT * FROM {giportfolio_chapters}
               WHERE pagenum= (
                  SELECT MAX(pagenum)
                    FROM {giportfolio_chapters}
                   WHERE giportfolioid= :giportfolioid AND userid = 0
               ) AND giportfolioid= :giportfolioid2 AND id= :chapterid
               ";
        $params = array('giportfolioid' => $giportfolioid, 'giportfolioid2' => $giportfolioid, 'chapterid' => $chapterid);
    } else {
        $sql = "SELECT * FROM {giportfolio_chapters}
               WHERE pagenum=(
                  SELECT MAX(pagenum)
                    FROM {giportfolio_chapters}
                   WHERE giportfolioid= :giportfolioid AND userid = 0
               ) AND giportfolioid= :giportfolioid2
               ";
        $params = array('giportfolioid' => $giportfolioid, 'giportfolioid2' => $giportfolioid);
    }

    return $DB->get_record_sql($sql, $params);
}

function giportfolio_get_first_userchapter($giportfolioid, $chapterid, $userid)
{ // Return the first user defined chapter.
    global $DB;

    $sql = "SELECT * FROM {giportfolio_chapters}
               WHERE pagenum=(
                  SELECT MIN(pagenum)
                    FROM {giportfolio_chapters}
                   WHERE giportfolioid= :giportfolioid AND userid = :userid
               ) AND giportfolioid= :giportfolioid2 AND id= :chapterid
               ";
    $params = array(
        'giportfolioid' => $giportfolioid, 'giportfolioid2' => $giportfolioid, 'chapterid' => $chapterid, 'userid' => $userid
    );

    return $DB->get_record_sql($sql, $params);
}

function giportfolio_check_user_chapter($chapter, $userid)
{ // Check if chapter is user defined one.
    if (!is_object($chapter)) {
        throw new coding_exception('Must pass full chapter object to giportfolio_check_user_chapter');
    }
    if ($chapter->userid && $chapter->userid != $userid) {
        throw new coding_exception('Chapter user does not match the user provided');
    }
    return (bool)($chapter->userid);
}

function giportfolio_delete_user_contributions($chapterid, $userid, $giportfolioid)
{
    // Delete user contributions from their chapters before deleting the chapter.
    global $DB;

    $sql = "SELECT * FROM {giportfolio_contributions}
               WHERE giportfolioid= :giportfolioid AND userid= :userid AND chapterid= :chapterid
               ";

    $params = array('giportfolioid' => $giportfolioid, 'userid' => $userid, 'chapterid' => $chapterid);

    $usercontributions = $DB->get_records_sql($sql, $params);

    if ($usercontributions) {
        foreach ($usercontributions as $usercontrib) {
            $delcontrib = new stdClass();
            $delcontrib->id = $usercontrib->id;
            $delcontrib->chapterid = $usercontrib->chapterid;
            $delcontrib->userid = $usercontrib->userid;

            $DB->delete_records('giportfolio_contributions', array(
                'id' => $delcontrib->id, 'userid' => $delcontrib->userid,
                'chapterid' => $delcontrib->chapterid
            ));
        }
    }
}

function giportfolio_delete_chapter_contributions($chapterid, $cmid, $giportfolioid)
{
    global $DB;

    $params = array(
        'giportfolioid' => $giportfolioid,
        'chapterid' => $chapterid
    );

    // Delete any attached files.
    $context = context_module::instance($cmid);
    $fs = get_file_storage();
    $contributions = $DB->get_records('giportfolio_contributions', $params, '', 'id');
    foreach ($contributions as $contrib) {
        $fs->delete_area_files($context->id, 'mod_giportfolio', 'contribution', $contrib->id);
        $fs->delete_area_files($context->id, 'mod_giportfolio', 'attachment', $contrib->id);
    }

    // Delete the contributions.
    $DB->delete_records('giportfolio_contributions', $params);
}

// Parent view of own child's activity functionality
function giportfolio_user_is_mentor($context, $user)
{
    global $DB;

    if (!is_enrolled($context, $user)) {
        $userfields = user_picture::fields('u');

        $sql = "SELECT u.id, $userfields
                FROM {role_assignments} ra, {context} c, {user} u
                WHERE ra.userid = :mentorid
                AND ra.contextid = c.id
                AND c.instanceid = u.id
                AND c.contextlevel = :contextlevel";

        $params = array(
            'mentorid' => $user->id,
            'contextlevel' => CONTEXT_USER
        );

        if ($users = $DB->get_records_sql($sql, $params)) {
            return [$users, true];
        }
    }

    return [null, false];
}

function giportfolio_mentor_allowed_to_contribute($instanceid)
{
    global $DB;
    return $DB->get_field('giportfolio', 'allowmentorcontrib', ['id' => $instanceid], IGNORE_MISSING);
}

function giportfolio_non_editing_teacher_allowed_to_contribute($instanceid)
{
    global $DB;
    return $DB->get_field('giportfolio', 'allownetcontribute', ['id' => $instanceid], IGNORE_MISSING);
}

function giportfolio_hide_show_contribution($instanceid) {
    global $DB;
    return $DB->get_field('giportfolio', 'hideshowcontribution', ['id' => $instanceid], IGNORE_MISSING);
}


function giportfolio_get_mentees_mentor($menteeid)
{
    global $DB;

    $sql = "SELECT u.id AS mentorid
            FROM mdl_user as u
            JOIN mdl_role_assignments ra ON ra.userid = u.id
            JOIN mdl_role role ON role.id = ra.roleid
            JOIN mdl_context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 30
            JOIN mdl_user child ON child.id = ctx.instanceid
            WHERE role.shortname = 'parent' and  child.id = {$menteeid};";

    $mentors = $DB->get_records_sql($sql);
    $ids = [];

    foreach ($mentors as $mentor) {
        $ids[] = $mentor->mentorid;
    }

    $ids = implode(',', $ids);

    return $ids;
}
//Part of Portfolios Updated chapters list.
function has_seen_contribution($contributionid)
{
    global $DB, $USER;

    $sql = "SELECT chapterid
            FROM mdl_giportfolio_follow_updates
            WHERE contributionid = $contributionid and userid = $USER->id;";
    $r = $DB->get_records_sql($sql);

    return $r;
}

function follow_updates_entry($contribution)
{
    global $DB, $USER;

    $data = new \stdClass();
    $data->giportfolioid = $contribution->giportfolioid;
    $data->chapterid = $contribution->chapterid;
    $data->contributionid = $contribution->id;
    $data->userid = $USER->id;

    $id = $DB->insert_record('giportfolio_follow_updates', $data, true);

    return $id;
}

function giportfolio_users_with_access($users, $course, $cmid)
{
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmid);
    $info = new \core_availability\info_module($cm);
    return $info->filter_user_list($users);
}

// Filter graders.
// To avoid sending notifications to users that have approved archetype at category level
function giportfolio_filter_graders($graders)
{
    global $COURSE;
    $context = \context_course::instance($COURSE->id);
    $roles = [1, 3, 4];
    $courseteacherids = array_keys(get_role_users($roles, $context, false, 'u.id'));
    $receiver = [];
    foreach ($graders as $grader) {
        if ((in_array($grader->id, $courseteacherids))) {
            $receiver[] = $grader;
        }
    }

    return $receiver;
}

/**
 * Render graph of contributors table. CGS customization.
 */
function giportfolio_graph_of_contributors($PAGE, $allusers, $context, $username, $listusersids, $perpage, $page, $giportfolio, $course, $cm)
{
    global $CFG, $DB, $OUTPUT, $COURSE;

    $chapters = giportfolio_preload_chapters($giportfolio);
    $chaptersid = [];
    $titles = [];
 
    $studentalias = get_string('studentgiportfolio', 'mod_giportfolio', get_student_alias($COURSE));

    foreach ($chapters as $chapter) {
        if (!$chapter->subchapter) {
            $titles[] =  '<div class="rotated-text-container"><span class="rotated-text">'. ($chapter->title).'</span></div>
                            <div class = "subchapter-icon">
                            <img class ="icon" alt ="Chapter" title = "Chapter" src="'. $OUTPUT->image_url('chapter', 'mod_giportfolio').'"/>
                        </div>
            ';
        } else {
            $titles[] = '<div class="rotated-text-container">
                              <span class="rotated-text">'.  ($chapter->title). '</span>
                        </div>
                        <div class = "subchapter-icon">
                            <img class ="icon" alt ="Subchapter" title = "Subchapter" src="'. $OUTPUT->image_url('subchapter_icon', 'mod_giportfolio').'"/>
                        </div>';
        }
        $chaptersid[] = $chapter->id;
    }
   
    // Look for chapters created by the student.

    $titles[] =  '<div class="rotated-text-container">
                      <span class="rotated-text">'.shorten_text( get_string('additionstitle', 'giportfolio')).'</span></div>
                      <div class = "subchapter-icon">
                            <img class ="icon" alt ="Added by student" title = "Added by student" src="'. $OUTPUT->image_url('addition_icon', 'mod_giportfolio').'"/>
                        </div>';
    list($insql, $inparams) = $DB->get_in_or_equal($chaptersid);

    $tablecolumns = array_merge(array('picture', 'fullname'), $titles);
    $extrafields = get_extra_user_fields($context);
    $tableheaders = array_merge(array('', get_string('fullnameuser')), $titles);

    require_once($CFG->libdir . '/tablelib.php');
    $table = new flexible_table('mod-giportfolio-graph-contribution');

    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($PAGE->url);

    $table->sortable(false);
    $table->column_class('picture', 'picture');
    $table->column_class('fullname', 'fullname');

    foreach ($table->column_class as $name => $column) {
        if (!in_array($name, ['picture', 'fullname', get_string('additionstitle', 'giportfolio')])) {  // These are the columns for the chapter titles          
            $table->column_class($name, 'completion-header');
        }
    }

    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'graphcontributors');
    $table->set_attribute('class', 'graphofcontributors generaltable flexible boxaligncenter');
    $table->set_attribute('width', '100%');

    // Start working -- this is necessary as soon as the niceties are over.
    $table->setup();

    $ufields = user_picture::fields('u', $extrafields);

    if ($where) {
        $where .= ' AND ';
    }

    if ($username) {
        $where .= ' (u.lastname like \'%' . $username . '%\' OR u.firstname like \'%' . $username . '%\' ) AND ';
    }

    $extratables = 'JOIN {giportfolio_chapters} ch ON ch.giportfolioid = :portfolioid';
    $params['portfolioid'] = $giportfolio->id;
    $sort = ' ORDER BY lastname';


    if (!empty($allusers)) {
        $select = "SELECT DISTINCT $ufields";

        $sql = ' FROM {user} u ' . $extratables .
            ' WHERE ' . $where . 'u.id IN (' . $listusersids . ') ';

        $pusers = $DB->get_records_sql($select . $sql . $sort, $params, $table->get_page_start(), $table->get_page_size());

        $table->pagesize($perpage, count($pusers));

        $offset = $page * $perpage;
        $rowclass = null;
        $endposition = $offset + $perpage;
        $currentposition = 0;

        foreach ($pusers as $puser) {

            if ($currentposition == $offset && $offset < $endposition) {
                $picture = $OUTPUT->user_picture($puser);
                $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $puser->id . '&amp;course=' . $course->id . '">' .
                    fullname($puser, has_capability('moodle/site:viewfullnames', $context)) . '</a>';

                $offset++;

                list($legends, $additions) = giportfolio_get_contributions_to_display($chaptersid, $giportfolio, $puser, $cm);

                $row = array_merge(array($picture, $userlink), $legends, $additions);
                $table->add_data($row, $rowclass);
            }

            $currentposition++;
        }
    } else {
        echo html_writer::tag('div', get_string('nosubmisson', 'mod_giportfolio'), array('class' => 'nosubmisson'));
    }

    $table->print_html();
}

function giportfolio_get_contributions_to_display($chaptersid, $giportfolio, $user, $cm)
{
    global $DB, $USER;

    list($insql, $inparams) = $DB->get_in_or_equal($chaptersid);

    // Get all the contributions done by this user.
    $sql = "SELECT id as 'contribid', chapterid FROM {giportfolio_contributions} WHERE chapterid $insql AND userid = $user->id";
    $contributions = $DB->get_records_sql($sql, $inparams);

    $nocontribution = html_writer::span('<i class = "fa">&#xf068;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('nocontrib', 'mod_giportfolio')]);
    $unseencontribution = html_writer::span('<i class = "fa">&#xf096;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('unseencontrib', 'mod_giportfolio')]);
    $seencontribution = html_writer::span('<i class = "fa">&#xf046;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('seencontrib', 'mod_giportfolio')]);
    $iconcomment =  html_writer::span('<i class = "fa">&#xf075;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('contrcomment', 'mod_giportfolio')]);
    $iconnocomment = html_writer::span('<i class = "fa">&#xf0e5;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('contrnocomment', 'mod_giportfolio')]);
    $iconcomments = html_writer::span('<i class = "fa">&#xf086</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('contrcomments', 'mod_giportfolio')]);



    // Filter the chapter ids.
    foreach ($contributions as $contribution) {

        if (!in_array($contribution->chapterid, $chaptersid)) {
            $chaptersid[] = $contribution->chapterid;
        }
    }

    // Users contribution ids only.
    $usercontrib = array_keys($contributions);
    $usercontrib = implode(',', $usercontrib);

    if ($usercontrib != '') {
        // Get all the contributions done by this user seen by the teacher.
        $sql = "SELECT contributionid, chapterid
                    FROM mdl_giportfolio_follow_updates
                    WHERE userid = $USER->id AND contributionid  IN ($usercontrib) AND giportfolioid = $giportfolio->id";

        $contributionsseen = $DB->get_records_sql($sql);
        $usercontrib = explode(',', $usercontrib); // Convert string to array again.
        $contributionsnotseen = array_diff($usercontrib, array_keys($DB->get_records_sql($sql))); // Id's of the contributions not seen.
        $cnotseen = [];

        foreach ($contributions as $contribution) {
            $contr = new \stdClass();

            if (in_array($contribution->contribid, $contributionsnotseen)) {
                $contr->chapterid = $contribution->chapterid;
                $contr->contribid = $contribution->contribid;
                $contr->totalcomment = giportfolio_count_contributions_comments($contribution->contribid);
                $cnotseen[] = $contr;
            }
        }

        foreach ($contributionsseen as $cseen) {
            $cseen->totalcomment = giportfolio_count_contributions_comments($cseen->contributionid);
        }
    }

    foreach ($chaptersid as $chapterid) {

        if ($usercontrib == '') {
            $links[] = $nocontribution;
            continue;
        }

        $url = new moodle_url('/mod/giportfolio/viewgiportfolio.php', array(
            'id' => $cm->id, 'chapterid' => $chapterid,
            'mentee' =>  $user->id,
            'cont' => 'yes'
        ));


        if (giportfolio_in_array($chapterid, $contributionsseen)) {
            
            list($countseen, $countcomments, $countnocomments) = giportfolio_count_new_or_seencontributions_for_chapter($chapterid, $contributionsseen);
            
            if ($countseen > 1) {
                $links[] = html_writer::tag('a', "$seencontribution $seencontribution", ['href' => $url, 'target' => '_blank']);
            } else {
                $links[] = html_writer::tag('a', " $seencontribution", ['href' => $url, 'target' => '_blank']);
            }
            // The chapter has been seen before and there are new contributions.
            if (giportfolio_in_array($chapterid, $cnotseen)) {
                $link = array_pop($links);
                if ($countnocomments == 0) {
                    array_push($links, $link . html_writer::tag('a', " $unseencontribution $iconnocomment", ['href' => $url, 'target' => '_blank']));
                } else {
                    array_push($links, $link . html_writer::tag('a', "$unseencontribution", ['href' => $url, 'target' => '_blank']));
                }
                
            }

            $link = array_pop($links);

            if ($countcomments == 0) {
                array_push($links, $link . html_writer::tag('a', "$iconnocomment", ['href' => $url, 'target' => '_blank']));
            } else if ($countcomments == 1) {
                array_push($links, $link . html_writer::tag('a', "$iconcomment", ['href' => $url, 'target' => '_blank']));
            } else if ($countcomments > 1) {
                array_push($links, $link . html_writer::tag('a', "$iconcomments", ['href' => $url, 'target' => '_blank']));
            }

        } else if (giportfolio_in_array($chapterid, $cnotseen)) {
            list($countseen, $countcomments, $countnocomments) = giportfolio_count_new_or_seencontributions_for_chapter($chapterid, $contributions);
            if ($chapterid == 119) {var_dump($countseen);}
            
            if ($countseen > 1) {
                $links[] = html_writer::tag('a', "$unseencontribution $unseencontribution", ['href' => $url, 'target' => '_blank']);
            } else {
                $links[] = html_writer::tag('a', "$unseencontribution", ['href' => $url, 'target' => '_blank']);
            }

            $link = array_pop($links);      

            if ($countcomments == 0) {
                array_push($links, $link . html_writer::tag('a', "$iconnocomment", ['href' => $url, 'target' => '_blank']));
            } else if ($countcomments == 1) {
                array_push($links, $link . html_writer::tag('a', "$iconcomment", ['href' => $url, 'target' => '_blank']));
            } else if ($countcomments > 1) {
                array_push($links, $link . html_writer::tag('a', "$iconcomments", ['href' => $url, 'target' => '_blank']));
            }
        } else {
            
            $links[] =  $nocontribution;
        }

       
    }

    $additions[] = giportfolio_get_user_generated_chapters_not_seen($giportfolio->id, $user->id, $cm, $usercontrib);

    return [$links, $additions];
}


// Helper functions for giportfolio_get_contributions_to_display.
function giportfolio_get_user_generated_chapters_not_seen($giportfolioid, $userid, $cm)
{
    global $DB, $PAGE, $USER;

    // Collect the chapters created by the student.
    $sql = "SELECT id FROM mdl_giportfolio_chapters WHERE giportfolioid = $giportfolioid AND userid = $userid;";

    $chapters = $DB->get_records_sql($sql);
    $chapterids = implode(',', array_keys($chapters));
    $contributionsseen = [];
    $links = '';
    $index = 0;

    if ($chapterids) {

        $sql = "SELECT id FROM mdl_giportfolio_contributions WHERE giportfolioid = $giportfolioid AND userid = $userid AND chapterid IN ($chapterids)";
        $cids = $DB->get_records_sql($sql);

        if ($cids) {
            $cids = implode(',', array_keys($DB->get_records_sql($sql)));
            $sql = "SELECT contributionid FROM mdl_giportfolio_follow_updates 
                    WHERE userid = $USER->id AND contributionid  IN ($cids) AND giportfolioid = $giportfolioid";
            $contributionsseen = array_keys($DB->get_records_sql($sql));
        }

        // In case the chapter has no content, by pass it.
        $sql = "SELECT gcont.id AS 'contribid', gcont.chapterid, gchap.title FROM mdl_giportfolio_contributions AS gcont
                JOIN  mdl_giportfolio_chapters AS gchap on  gcont.chapterid = gchap.id
                WHERE gcont.giportfolioid = $giportfolioid AND gcont.userid = $userid AND gcont.chapterid IN ($chapterids)";

        $countcontributions = $DB->get_records_sql($sql);
        $chaptercontributions = [];

        foreach ($countcontributions as $contribution) {

            if (in_array($contribution->contribid, $contributionsseen)) {
                continue;
            }

            $url = new moodle_url('/mod/giportfolio/viewcontribute.php', array(
                'id' => $cm->id, 'chapterid' => $contribution->chapterid,
                'userid' => $userid, 'cont' => 'no'
            ));

            $chaptercontributions[$contribution->chapterid] = ['url' => $url, 'title' => $contribution->title];
        }

        foreach ($chaptercontributions as $i => $chapter) {

            if (in_array($i, $contributionsseen)) {
                continue;
            }

            if ($index >= 3) {

                $params = [
                    'href' => $chapter['url'],
                    'target' => '_blank',
                    'class' => 'giportfolio-updatedch' . ' contributor_' . $userid,
                    'id' => 'contributor_' . $userid
                ];

                $links .= html_writer::tag("a", $chapter['title'],  $params);
            } else {
                $links .= html_writer::tag('a', $chapter['title'], ['href' => $chapter['url'], 'target' => '_blank']) . '<br>';
            }

            $index++;
        }

        $morethanthree = count($chaptercontributions) > 3;

        if ($morethanthree) {

            $params = ["class" => "giportfolio-more", "id" => $userid, 'title' => 'Show More'];
            $icon = '<i class = "fa">&#xf067;</i>';
            $links .= html_writer::span($icon, '', $params);
            $jsmodule = array(
                'name' => 'mod_giportfolio_morechapters',
                'fullpath' => new moodle_url('/mod/giportfolio/morechapters.js'),
                'contributorid' => $userid
            );

            $PAGE->requires->js_init_call('M.mod_giportfolio_showMore.init', array($userid), true, $jsmodule);
        }
    }

    return $links;
}

function giportfolio_in_array($chapterid, $contributions)
{

    foreach ($contributions as $contribution) {

        if ($contribution->chapterid == $chapterid) {
            return true;
        }
    }

    return false;
}

function giportfolio_count_new_or_seencontributions_for_chapter($chapterid, $contributions)
{
    $countseen = 0;
    $countcomments = 0;
    $countnocomments = 0;

    foreach ($contributions as $contribution) {
        if ($contribution->chapterid == $chapterid) {
            $countseen++;
            $countcomments += $contribution->totalcomment;
           
            if ($contribution->totalcomment == 0) {              
                $countnocomments++;
            }
        }
    }
  
    return array($countseen, $countcomments, $countnocomments);
}

function giportfolio_count_contributions_comments($contributionid){
    global $DB;

    $sql = "SELECT * FROM mdl_comments WHERE itemid = $contributionid;";
    $total = count($DB->get_records_sql($sql));

    return $total;
}


// End helper functions.

function giportfolio_submissionstables($context, $username, $currenttab, $giportfolio, $allusers, $listusersids, $perpage, $page, $cm, $url, $course, $quickgrade, $filter)
{
    global $CFG, $PAGE, $USER, $DB, $OUTPUT;
    $tabindex = 1; // Tabindex for quick grading tabbing; Not working for dropdowns yet.
    $extrafields = get_extra_user_fields($context);

    list($tablecolumns, $tableheaders, $showgradecol) = giportfolio_table_columns($giportfolio->id, $context);
        
    require_once($CFG->libdir . '/tablelib.php');
    $table = new flexible_table('mod-giportfolio-submissions');

    $table->define_columns($tablecolumns);
    $table->define_headers($tableheaders);
    $table->define_baseurl($PAGE->url);

    $table->sortable(true, 'lastname'); // Sorted by lastname by default.
    $table->collapsible(true);
    $table->initialbars(true);

    $table->column_suppress('picture');
    $table->column_suppress('fullname');
    $table->column_suppress('chaptersupdated');

    $table->column_class('picture', 'picture');
    $table->column_class('fullname', 'fullname');
    $table->column_class('lastupdate', 'lastupdate');
    $table->column_class('viewgiportfolio', 'viewgiportfolio');
    $table->column_class('chaptersupdated', 'chaptersupdated');

    if ( $showgradecol) {
        $table->column_class('grade', 'grade');
    }

    $table->column_class('feedback', 'feedback');
    $table->set_attribute('cellspacing', '0');
    $table->set_attribute('id', 'attempts');
    $table->set_attribute('class', 'submissions');
    $table->set_attribute('width', '100%');

    $table->no_sorting('lastupdate');
    $table->no_sorting('chaptersupdated');
    $table->no_sorting('feedback');
    $table->no_sorting('grade');
    $table->no_sorting('viewgiportfolio');

    // Start working -- this is necessary as soon as the niceties are over.
    $table->setup();
    // Construct the SQL.

    $extratables = '';
    list($where, $params) = $table->get_sql_where();
    if ($where) {
        $where .= ' AND ';
    }

    if ($username) {
        $where .= ' (u.lastname like \'%' . $username . '%\' OR u.firstname like \'%' . $username . '%\' ) AND ';
    }

    if ($currenttab == 'sincelastlogin') {
        $extratables = 'JOIN {giportfolio_contributions} c ON c.giportfolioid = :portfolioid
                    AND c.timemodified > :lastlogin
                    AND c.userid = u.id';

        $params['portfolioid'] = $giportfolio->id;
        $params['lastlogin'] = $USER->lastlogin;
    } else if ($currenttab == 'nocomments') {

        $extratables = 'JOIN {giportfolio_contributions} c ON c.giportfolioid = :portfolioid AND c.userid = u.id
                    LEFT JOIN {grade_grades} g ON g.itemid = :gradeid AND g.userid = u.id';
        $params['gradeid'] = $DB->get_field('grade_items', 'id', array(
            'itemtype' => 'mod', 'itemmodule' => 'giportfolio',
            'iteminstance' => $giportfolio->id
        ));
        $params['portfolioid'] = $giportfolio->id;
        $where .= "(g.feedback IS null OR g.feedback = '') AND ";
    }

    if ($sort = $table->get_sql_sort()) {
        $sort = ' ORDER BY ' . $sort;
    }

    $ufields = user_picture::fields('u', $extrafields);

    if (!empty($allusers)) {
        $select = "SELECT DISTINCT $ufields ";

        $sql = 'FROM {user} u ' . $extratables .
            ' WHERE ' . $where . 'u.id IN (' . $listusersids . ') ';

        $pusers = $DB->get_records_sql($select . $sql . $sort, $params, $table->get_page_start(), $table->get_page_size());

        $table->pagesize($perpage, count($pusers));

        $offset = $page * $perpage;
        $grademenu = make_grades_menu($giportfolio->grade);

        $rowclass = null;

        $endposition = $offset + $perpage;
        $currentposition = 0;

        $strview = get_string('view');
        $strnotstarted = get_string('notstarted', 'mod_giportfolio');
        $strprivate = get_string('private', 'mod_giportfolio');
        $strgrade = get_string('grade');
        $strcontribute = get_string('contribute', 'mod_giportfolio');

        foreach ($pusers as $puser) {
            $updatedchapters = '-';
            if ($currentposition == $offset && $offset < $endposition) {
                $picture = $OUTPUT->user_picture($puser);
                $usercontribution = giportfolio_get_user_contribution_status($giportfolio->id, $puser->id);
                $private = false;
                if (!$usercontribution) {
                    $private = $DB->record_exists('giportfolio_contributions', array(
                        'giportfolioid' => $giportfolio->id,
                        'userid' => $puser->id
                    ));
                }
                $statuspublish = '';
                $userfinalgrade = new stdClass();
                $userfinalgrade->grade = null;
                $userfinalgrade->str_grade = '-';
                if ($usercontribution) {
                    $updatedchapters = display_chapters_not_seen($giportfolio, $puser->id, $cm);
                    $lastupdated = date('l jS \of F Y ', $usercontribution);
                    $usergrade = grade_get_grades($course->id, 'mod', 'giportfolio', $giportfolio->id, $puser->id);
                    if ($usergrade->items) {
                        $gradeitemgrademax = $usergrade->items[0]->grademax;
                        $userfinalgrade = $usergrade->items[0]->grades[$puser->id];
                       
                        if ($quickgrade && !$userfinalgrade->locked) {
                            $attributes = array();
                            $attributes['tabindex'] = $tabindex++;
                            $menu = html_writer::select(
                                make_grades_menu($giportfolio->grade),
                                'menu[' . $puser->id . ']',
                                round(($userfinalgrade->grade), 0),
                                array(-1 => get_string('nograde')),
                                $attributes
                            );
                            $userfinalgrade->grade = '<div id="g' . $puser->id . '">' . $menu . '</div>';
                        }

                        if ($userfinalgrade->feedback && !$quickgrade) {
                            $feedback = $userfinalgrade->feedback;
                        } else if ($quickgrade) {
                            $feedback = '<div id="feedback' . $puser->id . '">'
                                . '<textarea tabindex="' . $tabindex++ . '" name="submissionfeedback[' . $puser->id . ']" id="submissionfeedback'
                                . $puser->id . '" rows="2" cols="20">' . ($userfinalgrade->feedback) . '</textarea></div>';
                        } else {
                            $feedback = '-';
                        }
                    }
                } else {
                    $lastupdated = '-';
                    $updatedchapters = '-';
                    $feedback = '-';
                    $rowclass = '';
                }

                if ($usercontribution) {
                    $params = array('id' => $cm->id, 'userid' => $puser->id);
                    $cid = giportfolio_get_user_default_chapter($giportfolio->id, $puser->id);
                    $paramscontrib = array('id' => $cm->id, 'mentee' => $puser->id, 'chapterid' => $cid->chapterid, 'cont' => 'yes');

                    $viewurl = new moodle_url('/mod/giportfolio/viewcontribute.php', $params);
                    $gradeurl = new moodle_url('/mod/giportfolio/updategrade.php', $params);
                    $contribute = new moodle_url('/mod/giportfolio/viewgiportfolio.php', $paramscontrib);
                    $statuspublish = html_writer::link($viewurl, $strview);
                    $statuspublish .= ' | ' . html_writer::link($gradeurl, $strgrade);
                    $statuspublish .= ' | ' . html_writer::link($contribute, $strcontribute);
                    $rowclass = '';
                } else if ($private) {
                    $statuspublish = $strprivate;
                    $rowclass = 'late';
                } else {
                    $statuspublish = $strnotstarted;
                    $rowclass = 'late';
                    $paramscontrib = array('id' => $cm->id, 'mentee' => $puser->id, 'cont' => 'contribution');
                    $contribute = new moodle_url('/mod/giportfolio/viewgiportfolio.php', $paramscontrib);
                    $url->param('cont', 'contribution');
                    $statuspublish .= ' | ' . html_writer::link($contribute, $strcontribute);
                }

                $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $puser->id . '&amp;course=' . $course->id . '">' .
                    fullname($puser, has_capability('moodle/site:viewfullnames', $context)) . '</a>';
                $extradata = array();
                foreach ($extrafields as $field) {
                    $extradata[] = $puser->{$field};
                }
                $aux = $showgradecol ? array($lastupdated, $statuspublish, $updatedchapters, $userfinalgrade->grade, $feedback) : 
                array($lastupdated, $statuspublish, $updatedchapters, $feedback);
              
                $row = array_merge(
                    array($picture, $userlink),
                    $extradata,
                    $aux
                );
                $offset++;
                $table->add_data($row, $rowclass);
            }
            $currentposition++;
        }
        $table->print_html();
    } else {
        echo html_writer::tag('div', get_string('nosubmisson', 'mod_giportfolio'), array('class' => 'nosubmisson'));
    }

     // Print quickgrade form around the table.
     if ($quickgrade && $table->started_output && !empty($allusers)) {
        $savefeedback = html_writer::empty_tag('input', array(
                'type' => 'submit', 'name' => 'fastg',
                'value' => get_string('saveallfeedback', 'mod_giportfolio')
        ));
        echo html_writer::tag('div', $savefeedback, array('class' => 'fastgbutton'));
        echo html_writer::end_tag('form');

    } else if ($quickgrade) {
        echo html_writer::end_tag('form');
    }
    // Only give the optional settings if the grading setting is not none.
    if ($showgradecol) {

        // Mini form for setting user preference.
        $formaction = new moodle_url('/mod/giportfolio/submissions.php', array('id' => $cm->id));
        $mform = new MoodleQuickForm('optionspref', 'post', $formaction, '', array('class' => 'optionspref'));
    
        $mform->addElement('hidden', 'updatepref');
        $mform->setDefault('updatepref', 1);
        $mform->addElement('header', 'qgprefs', get_string('optionalsettings', 'giportfolio'));
    
        $mform->setDefault('filter', $filter);
    
        $mform->addElement('text', 'perpage', get_string('pagesize', 'giportfolio'), array('size' => 1));
        $mform->setDefault('perpage', $perpage);
    
        $mform->addElement('checkbox', 'quickgrade', get_string('quickgrade', 'giportfolio'));
        $mform->setDefault('quickgrade', $quickgrade);
        $mform->addHelpButton('quickgrade', 'quickgrade', 'giportfolio');
    
        $mform->addElement('submit', 'savepreferences', get_string('savepreferences'));
        // End table.
        $mform->display();
    }
}
/**
 * File browsing support class
 */
class giportfolio_file_info extends file_info
{
    protected $course;
    protected $cm;
    protected $areas;
    protected $filearea;

    public function __construct($browser, $course, $cm, $context, $areas, $filearea)
    {
        parent::__construct($browser, $context);
        $this->course = $course;
        $this->cm = $cm;
        $this->areas = $areas;
        $this->filearea = $filearea;
    }

    /**
     * Returns list of standard virtual file/directory identification.
     * The difference from stored_file parameters is that null values
     * are allowed in all fields
     * @return array with keys contextid, filearea, itemid, filepath and filename
     */
    public function get_params()
    {
        return array(
            'contextid' => $this->context->id,
            'component' => 'mod_giportfolio',
            'filearea' => $this->filearea,
            'itemid' => null,
            'filepath' => null,
            'filename' => null
        );
    }

    /**
     * Returns localised visible name.
     * @return string
     */
    public function get_visible_name()
    {
        return $this->areas[$this->filearea];
    }

    /**
     * Can I add new files or directories?
     * @return bool
     */
    public function is_writable()
    {
        return false;
    }

    /**
     * Is directory?
     * @return bool
     */
    public function is_directory()
    {
        return true;
    }

    /**
     * Returns list of children.
     * @return array of file_info instances
     */
    public function get_children()
    {
        global $DB;

        $children = array();
        $chapters = $DB->get_records(
            'giportfolio_chapters',
            array('giportfolioid' => $this->cm->instance),
            'pagenum',
            'id, pagenum'
        );
        foreach ($chapters as $itemid => $unused) {
            if ($child = $this->browser->get_file_info($this->context, 'mod_giportfolio', $this->filearea, $itemid)) {
                $children[] = $child;
            }
        }
        return $children;
    }

    /**
     * Returns parent file_info instance
     * @return file_info or null for root
     */
    public function get_parent()
    {
        return $this->browser->get_file_info($this->context);
    }
}
/**
 * Get the alias given to student role. CGS
 */
function get_student_alias($course)
{
    global $DB;
    $coursecontext = context_course::instance($course->id);
    $alias = 'Student'; // default;    
    $name = $DB->get_field('role_names', 'name', array('contextid' => $coursecontext->id, 'roleid' => 5));

    $alias = !$name ? $alias : $name;

    return $alias;
}

/**
 * Check if the user is a non editing teacher
 */
function is_non_editing_teacher()
{
    global $COURSE, $USER;
    // Allow non editing teachers to contribute
    $contextcourse = \context_course::instance($COURSE->id);
    $coursenoneditingteachers = array_keys(get_role_users('4', $contextcourse, false, 'u.id'));
    if (in_array(intval($USER->id), $coursenoneditingteachers)) {
        return true;
    }

    return false;
}

// Return true if the users role is student.
function giportfolio_is_student_in_this_course() {
    global $COURSE, $USER;
    $contextcourse = \context_course::instance($COURSE->id);
    $coursestudents = array_keys(get_role_users('5', $contextcourse, false, 'u.id'));
    if (in_array(intval($USER->id), $coursestudents)) {
        return true;
    }

    return false;
}
// CGS costumisation. If grading is set to none, then  hide all grade references.
function giportfolio_table_columns($giportfolioid, $context) {
    global $DB;

    $grade = $DB->get_field('giportfolio', 'grade', array('id' => $giportfolioid));
    $extrafields = get_extra_user_fields($context);
    $tablecolumns = [];
    $extrafieldnames = array();
    $showgradecol = false;

    foreach ($extrafields as $field) {
        $extrafieldnames[] = get_user_field_name($field);
    }

    if ($grade != 0) {
        $colums = array('lastupdate', 'viewgiportfolio', 'chaptersupdated', 'grade', 'feedback');
        $columntitles =   array(
            get_string('lastupdated', 'giportfolio'), get_string('viewgiportfolio', 'giportfolio'),
            get_string('chaptersupdated', 'giportfolio'), get_string('grade'), get_string('feedback')
        );
        $showgradecol = true;
    } else {
        $colums = array('lastupdate', 'viewgiportfolio', 'chaptersupdated', 'feedback');
        $columntitles = array(
            get_string('lastupdated', 'giportfolio'), get_string('viewgiportfolio', 'giportfolio'),
            get_string('chaptersupdated', 'giportfolio'), get_string('feedback')
        );
    }

    $tablecolumns = array_merge(array('picture', 'fullname'), $extrafields,  $colums);
    $tableheaders = array_merge(array('', get_string('fullnameuser')), $extrafieldnames, $columntitles);
      
    return array($tablecolumns, $tableheaders, $showgradecol);
}
