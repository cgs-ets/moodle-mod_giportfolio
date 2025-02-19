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
 * giportfolio printing
 *
 * @package    giportfoliotool
 * @subpackage print
 * @copyright  2012 Synergy Learning / Manolescu Dorel based on book module
 * @copyright  2022 CGS  / Veronica Bermegui based on book module
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__) . '/../../../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;

$id = required_param('id', PARAM_INT); // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.
$userid = optional_param('userid', $USER->id, PARAM_INT); // User ID.

// Security checks START - teachers and students view.
$cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/giportfolio:view', $context);
require_capability('giportfoliotool/print:print', $context);


// Check all variables.
if ($chapterid) {
    // Single chapter printing - only visible!
    $chapter = $DB->get_record('giportfolio_chapters', array(
        'id' => $chapterid, 'giportfolioid' => $giportfolio->id
    ), '*', MUST_EXIST);
    if ($chapter->userid && !$chapter->userid == $USER->id) {
        throw new moodle_exception('notyourchapter', 'mod_giportfolio');
    }
} else {
    // Complete giportfolio.
    $chapter = false;
}

$PAGE->set_url('/mod/giportfolio/print.php', array('id' => $id, 'chapterid' => $chapterid, 'userid' => $userid));

$PAGE->set_pagelayout("embedded");

// Security checks END.

// Read chapters.
$chapters = giportfolio_preload_chapters($giportfolio);
$additionalchapters = giportfolio_preload_userchapters($giportfolio);

if ($additionalchapters) {
    $chapters = $chapters + $additionalchapters;
}

unset($id);
unset($chapterid);

$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strtop = get_string('top', 'mod_giportfolio');

// Page header.
$strtitle = format_string($giportfolio->name, true, array('context' => $context));
$PAGE->set_title($strtitle);
$PAGE->set_heading($strtitle);
$PAGE->requires->css('/mod/giportfolio/tool/print/print.css');

$renderer = $PAGE->get_renderer('giportfoliotool_print');

// Begin page output.
echo $OUTPUT->header();

if ($chapter) {
    if ($chapter->hidden) {
        require_capability('mod/giportfolio:viewhiddenchapters', $context);
    }
    \giportfoliotool_print\event\chapter_printed::create_from_chapter($giportfolio, $context, $chapter)->trigger();
    $page = new giportfoliotool_print\output\print_giportfolio_chapter_page($giportfolio, $cm, $chapter, $userid);
} else {
    \giportfoliotool_print\event\giportfolio_printed::create_from_giportfolio($giportfolio, $context)->trigger();
    $page = new giportfoliotool_print\output\print_giportfolio_page($giportfolio, $cm, $userid, $chapters);
}

echo $renderer->render($page);

// Finish page output.
echo $OUTPUT->footer();
