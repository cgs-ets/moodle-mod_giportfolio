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
 * Move giportfolio user chapter
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
$up = optional_param('up', 0, PARAM_BOOL);

$mentor = optional_param('mentor', 0, PARAM_INT); // Mentor ID
$mentee = optional_param('mentee', 0, PARAM_INT);
$contribute = optional_param('cont', 'no', PARAM_RAW); // When teacher is contributing.

$cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);
require_sesskey();

$context = context_module::instance($cm->id);
$userid = $mentee != 0 ? $mentee : $USER->id;

$chapter = $DB->get_record('giportfolio_chapters', array(
    'id' => $chapterid, 'giportfolioid' => $giportfolio->id,
    'userid' => $userid
    ), '*', MUST_EXIST);

$oldchapters = $DB->get_records('giportfolio_chapters', array(
    'giportfolioid' => $giportfolio->id, 'userid' => $userid
    ), 'pagenum', 'id, pagenum, subchapter');

$lastpage = giportfolio_get_last_chapter($giportfolio->id);

$nothing = 0;

$chapters = array();
$chs = 0;
$che = 0;
$ts = 0;
$te = 0;
// Create new ordered array and find chapters to be moved.
$i = ($lastpage->pagenum + 1);

$found = 0;
foreach ($oldchapters as $ch) {
    $chapters[$i] = $ch;
    if ($chapter->id == $ch->id) {
        $chs = $i;
        $che = $chs;
        if ($ch->subchapter) {
            $found = 1; // Subchapter moves alone.
        }
    } else if ($chs) {
        if (!$found) {
            if ($ch->subchapter) {
                $che = $i; // Chapter with subchapter(s).
            } else {
                $found = 1;
            }
        }
    }
    $i++;
}

// Find target chapter(s).
if ($chapters[$chs]->subchapter) { // Moving single subchapter up or down.
    if ($up) {
        if ($chs == $lastpage->pagenum + 2) {
            $nothing = 1; // Already first.
        } else {
            $ts = $chs - 1;
            $te = $ts;
        }
    } else { // Down.
        if ($che == (count($chapters) + $lastpage->pagenum)) {
            $nothing = 1; // Already last.
        } else {
            $ts = $che + 1;
            $te = $ts;
        }
    }
} else { // Moving chapter and looking for next/previous chapter.
    if ($up) { // Up.
        if ($chs == 1) {
            $nothing = 1; // Already first.
        } else {
            $te = $chs - 1;
            for ($i = $chs - 1; $i >= 1; $i--) {
                if ($chapters[$i]->subchapter) {
                    $ts = $i;
                } else {
                    $ts = $i;
                    break;
                }
            }
        }
    } else { // Down.
        if ($che == count($chapters) + $lastpage->pagenum) {
            $nothing = 1; // Already last.
        } else {
            $ts = $che + 1;
            $found = 0;
            for ($i = $che + 1; $i <= (count($chapters) + $lastpage->pagenum); $i++) {
                if ($chapters[$i]->subchapter) {
                    $te = $i;
                } else {
                    if ($found) {
                        break;
                    } else {
                        $te = $i;
                        $found = 1;
                    }
                }
            }
        }
    }
}

// Recreated newly sorted list of chapters.
if (!$nothing) {
    $newchapters = array();

    if ($up) {
        if ($ts > 1) {
            for ($i = ($lastpage->pagenum + 1); $i < $ts; $i++) {
                $newchapters[] = $chapters[$i];
            }
        }
        for ($i = $chs; $i <= $che; $i++) {
            $newchapters[$i] = $chapters[$i];
        }
        for ($i = $ts; $i <= $te; $i++) {
            $newchapters[$i] = $chapters[$i];
        }
        if ($che < count($chapters)) {
            for ($i = $che; $i <= count($chapters); $i++) {
                $newchapters[$i] = $chapters[$i];
            }
        }
    } else {
        if ($chs > 1) {
            for ($i = ($lastpage->pagenum + 1); $i < $chs; $i++) {
                $newchapters[] = $chapters[$i];
            }
        }
        for ($i = $ts; $i <= $te; $i++) {
            $newchapters[$i] = $chapters[$i];
        }
        for ($i = $chs; $i <= $che; $i++) {
            $newchapters[$i] = $chapters[$i];
        }
        if ($te < count($chapters)) {
            for ($i = $te; $i <= count($chapters); $i++) {
                $newchapters[$i] = $chapters[$i];
            }
        }
    }

    // Store chapters in the new order.
    $i = ($lastpage->pagenum + 1);
    foreach ($newchapters as $ch) {
        $ch->pagenum = $i;
        $DB->update_record('giportfolio_chapters', $ch);
        $i++;
    }
}

giportfolio_preload_userchapters($giportfolio); // Fix structure.

redirect('viewgiportfolio.php?id='.$cm->id.'&chapterid='.$chapter->id.'&useredit=1&mentor='.$mentor.'&mentee='.$mentee.'&cont=' . $contribute);

