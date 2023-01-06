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
 * Giportfolio import base don booktool  import
 *
 * @package    giportfolio_importhtml
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @package    booktool_importhtml
 * @copyright  2004-2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/locallib.php');

$id        = required_param('id', PARAM_INT);           // Course Module ID.
$chapterid = optional_param('chapterid', 0, PARAM_INT); // Chapter ID.

$cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, false, $cm);

$context = \context_module::instance($cm->id);
require_capability('giportfoliotool/importhtml:import', $context);

$PAGE->set_url('/mod/giportfolio/tool/importhtml/index.php', array('id' => $id));
// $PAGE->add_body_class('limitedwidth');
$PAGE->set_title($giportfolio->name);
$PAGE->set_heading($course->fullname);

// Prepare the page header.
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

$posturl = $chapterid == 0 ? $CFG->wwwroot . "/mod/giportfolio/view.php?id=$cm->id" : $CFG->wwwroot ."/mod/giportfolio/viewgiportfolio.php?id=$cm->id&chapterid=$chapterid";
$data = new stdClass();
$data->actionurl = $posturl;
$data->sesskey = sesskey();
$data->cm = $id;
$data->giportfolioid = $giportfolio->id;
$data->viewurl = $posturl;

echo $OUTPUT->render_from_template('giportfoliotool_importhtml/import_chapter_droptarget', $data);

$PAGE->requires->js_call_amd('giportfoliotool_importhtml/import_chapter_control', 'init', ['']);


echo $OUTPUT->footer();
