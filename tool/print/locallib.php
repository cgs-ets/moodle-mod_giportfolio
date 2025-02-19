<?php
// This file is part of giportfolio plugin for Moodle - http://moodle.org/
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
 * HTML import lib
 *
 * @package    giportfoliotool
 * @subpackage print
 * @copyright  2011 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__) . '/lib.php');
global $CFG;
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');

function get_contribution_author($id) {
    global $DB;

    $author = $DB->get_record('user', ['id' => $id], 'firstname, lastname');
    return $author;
}


function get_total_hours($giportfolioid, $userid) {

    global $DB;

    $sql = "SELECT SUM(gcont.numhours) AS total_hours
            FROM (
                SELECT DISTINCT gcont.id, gcont.numhours
                FROM {giportfolio} gi
                JOIN {giportfolio_chapters} gc ON gi.id = gc.giportfolioid
                JOIN {giportfolio_contributions} gcont ON gcont.giportfolioid = gi.id
                WHERE gi.id = :giportfolioid AND gcont.userid = :userid
            ) AS gcont";

    $params = ['giportfolioid' => $giportfolioid, 'userid' => $userid];

    $totalhours = $DB->get_field_sql($sql, $params);
    $totalhours = number_format($totalhours, 2);

    return $totalhours;

}

function get_total_hours_by_chapter_contribution($chapterid, $userid) {

    global $DB;

    $sql= "SELECT SUM (gcont.numhours)
            FROM {giportfolio_contributions} gcont
            WHERE gcont.chapterid = :chapterid and gcont.userid = :userid";
    $params = ['chapterid' => $chapterid, 'userid' => $userid];
    $hoursperchapter = $DB->get_field_sql($sql, $params);
    $hoursperchapter = number_format($hoursperchapter, 2);

    return $hoursperchapter;

}

// Portfolio with hours are used for service learning. Where the student will add their chapters
// The parent portfolio will provide two chapters Begin and End.
// The students chapters will have to be printed in between the Begin and End chapters.
function reorder_toc($chapters) {

    $studentchapters = [];
    $newstruct = [];
    $teacherchapters = [];

    foreach ($chapters as $ch) {
        if ($ch->userid ==  0) {
            $teacherchapters [] = $ch;
        } else {
            $studentchapters[] = $ch;
        }

    }

    // Reorganize chapters as: first teacher chapter, then all student chapters, then remaining teacher chapters
    $newstruct = array_merge(
    array_slice($teacherchapters, 0, 1), // First teacher chapter
    $studentchapters,                   // All student chapters
    array_slice($teacherchapters, 1) );   // Remaining teacher chapters
    // Reindex the array by 'userid'
    $chapters = array_column($newstruct, null, 'id');

   return $chapters;
}