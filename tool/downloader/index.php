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
 * Downloader index
 *
 * @package    giportfoliotool_downloader
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/locallib.php');
require_once('downloader_form.php');

$id            = required_param('id', PARAM_INT);           // Course Module ID.
$cm            = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$action        = optional_param('download', 0, PARAM_INT);
$itemids       = optional_param('itemids', '', PARAM_TEXT);
$items         = optional_param('items', '', PARAM_TEXT);
$selectedusers = optional_param('selectedusers', '', PARAM_TEXT);
$chapterid     = optional_param('chapterid', 0, PARAM_INT);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', array('id' => $cm->instance), '*', MUST_EXIST);

if ($action) {
    giportfoliotool_downloader_download_files($items, $giportfolio->name);

}


require_login($course, false, $cm);

$context = \context_module::instance($cm->id);
require_capability('giportfoliotool/importhtml:import', $context);

$PAGE->set_url('/mod/giportfolio/tool/downloader/index.php', array('id' => $id));
$PAGE->set_title($giportfolio->name);
$PAGE->set_heading($course->fullname);

// Prepare the page header.
$strgiportfolio = get_string('modulename', 'mod_giportfolio');
$strgiportfolios = get_string('modulenameplural', 'mod_giportfolio');


echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

$mfordata = new stdClass;
$mformdata->giportfolioid = $giportfolio->id;
$mformdata->moduleid = $id;
$mfordata->cm = $cm;
$mform = new downloader_form(null, ['giportfolioid' => $giportfolio->id, "id" => $id, 'chapterid' => $chapterid]);
$printtable = false;
$firstaccess = true;
if ($mform->is_cancelled()) {
    $url = ($chapterid == 0) ? $CFG->wwwroot . "/mod/giportfolio/view.php?id=$cm->id" : $CFG->wwwroot ."/mod/giportfolio/viewgiportfolio.php?id=$cm->id&chapterid=$chapterid";
    redirect($url);
    $firstaccess = false;
} else if ($fromform = $mform->get_data()) {
    // Table giportfoliotool_print
    // Work out the sql for the table.
    $chapters = implode(',', $fromform->chapters);
    $students = giportfoliotool_downloader_get_students($giportfolio->id, $chapters, $id);
    $downloadformdata = giportfoliotool_downloader_get_downloader_form_context($id, $cm->id);

    $printtable = true;
    $nocontributions = count($students) == 0;
    $firstaccess = false;

}

$mform->display();

if ($printtable && !$nocontributions) {
    echo $OUTPUT->render_from_template('giportfoliotool_downloader/students_table', $students);
    echo $OUTPUT->render_from_template('giportfoliotool_downloader/downloader_form', $downloadformdata);
    $PAGE->requires->js_call_amd('giportfoliotool_downloader/downloader_control', 'init');
} else if (!$firstaccess) {
    echo $OUTPUT->render_from_template('giportfoliotool_downloader/contributions_not_found', '');
}



echo $OUTPUT->footer();
