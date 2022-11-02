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
 * HTML import lib
 *
 * @package    giportfoliotool_importhtml
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');

/**
 * $data object with the info required.
 */
function toolgiportfolio_importhtml_add_chapters_to_portfolio(stdClass $data) {
    global $DB;

    $chapterids = implode(',', $data->chapterids);

    $sql = "SELECT * FROM mdl_giportfolio_chapters where id in ($chapterids) ORDER BY pagenum ";
    $rs = $DB->get_recordset_sql($sql);

    $qry = "SELECT max(pagenum) as pagenum FROM mdl_giportfolio_chapters where giportfolioid = $data->giportfolioid";
    $numpage = $DB->get_record_sql($qry);
    $numpage = $numpage->pagenum;

    $mapchapterids = []; // Set a link between the cloned chapter id and the original chapter id.
    $result = 'OK';
    $fs = get_file_storage();
    $component = 'mod_giportfolio';
    $hasitems = false;

    foreach ($rs as $record) {
        $hasitems = true;
        $chid = $record->id;
        unset($record->id);
        $record->giportfolioid = $data->giportfolioid;

        // Set the title again, it might have been changed on the UI.

        $newtitle = toolgiportfolio_importhtml_chapter_title($chid, $data->chaptersdetails);
        $record->title = $newtitle == null ? $record->title : $newtitle;
        $numpage++;
        $record->pagenum = $numpage;
        $record->importsrc = "Chapter ID: $chid";

        $mapchapterids[$chid] = $DB->insert_record('giportfolio_chapters', $record, true, true);
    }

    if (!$hasitems) { // In this case, the dragged chapter was from a portfolio that is not in this DB.
        throw new Exception ("Fail");
    }

    // Copy the files that are in the chapter intro (if they are).

    foreach ($mapchapterids as $originalchid => $newchid) {
        $cmodule = toolgiportfolio_importhtml_get_course_module($data->chapterandcoursemodule, $originalchid);
        if ($cmodule != null) {

            $context = \context_module::instance($cmodule);

            if ($files = $fs->get_area_files($context->id, $component, 'chapter', $originalchid, "filename", true)) {
                $contexttarget = \context_module::instance($data->cm);
                foreach ($files as $file) {
                    $newrecord = new \stdClass();
                    $newrecord->contextid = $contexttarget->id;
                    $newrecord->itemid = $newchid; // Is the contribution id.
                    $fs->create_file_from_storedfile($newrecord, $file);
                }
            }
        }
    }

    $contributions = toolgiportfolio_importhtml_copy_contributions($data->chapterids, $mapchapterids, $data->giportfolioid, $data->chapterandcoursemodule);

    if (!empty($contributions)) {
        toolgiportfolio_importhtml_copy_files($data->cm, $contributions);
        toolgiportfolio_importhtml_copy_comments($data->cm, $contributions);
        toolgiportfolio_importhtml_copy_graph_contributors($contributions);
    }

    $rs->close();

    return $result;
}

function toolgiportfolio_importhtml_chapter_title($chid, $chdetails) {

    foreach ($chdetails as $ch) {

        if ($ch->id == $chid) {
            return $ch->title;
        }

        if (isset($ch->subchapteraux)) { // Look for the title in the subchpaters too.
            foreach ($ch->subchapteraux as $subch) {
                if ($subch->id == $chid) {
                    return $subch->title;
                }
            }
        }
    }
}

/**
 * $chapterids: Chapters to copy
 * $newchapterids: Chapters copied ID
 * return array that maps the original contribution id and the new one.
 * We already copied the chapters in the DB. Now we need to copy the contributions
 */
function toolgiportfolio_importhtml_copy_contributions($chapterids, $newchapterids, $giportfolioid, $chapterandcoursemodule) {
    global $DB;

    $chapterids = implode(",", $chapterids);

    $sql = "SELECT * FROM mdl_giportfolio_contributions where chapterid in ($chapterids)";

    $rs = $DB->get_recordset_sql($sql);

    $contributions = [];

    try {

        foreach ($rs as $record) {

            $oldchid = $record->chapterid;
            $originalcid = $record->id;
            unset($record->id);
            $record->chapterid = $newchapterids[$record->chapterid];
            $record->giportfolioid = $giportfolioid;

            $cid = $DB->insert_record('giportfolio_contributions', $record, true, true);
            $data = new stdClass();
            $data->itemid = $cid;
            $data->chapterid = $record->chapterid;
            $data->giportfolioid = $giportfolioid;

            $data->contextmodule = toolgiportfolio_importhtml_get_course_module($chapterandcoursemodule, $oldchid);
            $contributions[$originalcid] = $data;
        }
    } catch (Exception $e) {
        // TODO.
    }

    $rs->close();

    return $contributions;
}

function toolgiportfolio_importhtml_get_course_module($chapterandcoursemodule, $chid) {

    foreach ($chapterandcoursemodule as $ch) {
        if ($ch->chapterid == $chid) {
            return $ch->contextmodule;
        }
    }

    return null;
}

function toolgiportfolio_importhtml_copy_files($cm, $contributions) {
    $contexttarget = \context_module::instance($cm);
    $fs = get_file_storage();
    $component = 'mod_giportfolio';

    foreach ($contributions as $i => $contribution) {

        $context = \context_module::instance($contribution->contextmodule);

        if ($files = $fs->get_area_files($context->id, $component, 'contribution', $i, "filename", true)) {

            foreach ($files as $file) {
                $newrecord = new \stdClass();
                $newrecord->contextid = $contexttarget->id;
                $newrecord->itemid = $contribution->itemid; // Is the contribution id.
                $fs->create_file_from_storedfile($newrecord, $file);
            }
        }

        if ($attachments = $fs->get_area_files($context->id, $component, 'attachment', $i, "filename", true)) {

            foreach ($attachments as $attachment) {
                $newrecord = new \stdClass();
                $newrecord->contextid = $contexttarget->id;
                $newrecord->itemid = $contribution->itemid; // Is the contribution id.
                $fs->create_file_from_storedfile($newrecord, $attachment);
            }
        }
    }
}

/**
 * Collect the comments made in the contributions being copied.
 */
function toolgiportfolio_importhtml_copy_comments($cm, $contributions) {

    global $DB;

    if (count($contributions) == 0) {
        return;
    }

    $contexttarget = \context_module::instance($cm);
    $commentarea = "giportfolio_contribution";
    $component = "mod_giportfolio";
    $olditemids = implode(",", array_keys($contributions));

    $sql = "SELECT * FROM mdl_comments WHERE commentarea = '$commentarea' AND component = '$component' AND itemid IN ($olditemids)";

    $rs = $DB->get_recordset_sql($sql);

    foreach ($rs as $record) {
        unset($record->id);
        $record->contextid = $contexttarget->id;
        $record->itemid = ($contributions[$record->itemid])->itemid;

        $DB->insert_record('comments', $record, true, true);
    }

    $rs->close();
}

/**
 * Collect the data from graph of contributors.
 */
function toolgiportfolio_importhtml_copy_graph_contributors($contributions) {
    global $DB;

    $ocontributionid = implode(",", array_keys($contributions)); // Get the original contribution ids.
    $sql = "SELECT * FROM mdl_giportfolio_follow_updates WHERE contributionid IN ($ocontributionid)";

    $rs = $DB->get_recordset_sql($sql);

    foreach ($rs as $record) {

        unset($record->id);
        $record->chapterid = ($contributions[$record->contributionid])->chapterid;
        $record->giportfolioid = ($contributions[$record->contributionid])->giportfolioid;
        $record->contributionid = ($contributions[$record->contributionid])->itemid;

        $DB->insert_record('giportfolio_follow_updates', $record, true, true);
    }

    $rs->close();
}
