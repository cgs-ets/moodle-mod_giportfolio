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
 * External giportfolio API
 *
 * @package    giportfolio_tool
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/user/externallib.php");
require_once(__DIR__ . '/locallib.php');
/**
 * Assign functions
 * @copyright 2012 Paul Charsley
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class giportfoliotool_external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */

    public static function import_chapters_parameters() {
        return new external_function_parameters(
            array(
                'chapterids' => new external_value(PARAM_RAW, 'chapter idd to import'),
                'giportfolioid' => new external_value(PARAM_RAW, 'origin giportfiod id'),
                'cm' => new external_value(PARAM_RAW, 'course module id'),
                'chapterandcoursemodule' => new external_value(PARAM_RAW, 'Chapter id and its context module id'),
                'chaptersdetails' => new external_value(PARAM_RAW, 'Contains all the chapters details'),
            )
        );
    }

    /**
     *
     * @global type $COURSE
     * @global type $DB
     * @param string $users
     * @param string $chapters
     * @return array
     */
    public static function import_chapters($chapterids, $giportfolioid, $cm, $chapterandcoursemodule, $chaptersdetails) {
        global $COURSE, $DB;

        $context = \context_course::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::import_chapters_parameters(),
            array(
                'chapterids' => $chapterids,
                'giportfolioid' => $giportfolioid,
                'cm' => $cm,
                'chapterandcoursemodule' => $chapterandcoursemodule,
                'chaptersdetails' => $chaptersdetails
            )
        );

        $chapterids = json_decode($chapterids);
        $chapterandcoursemodule = json_decode($chapterandcoursemodule);
        $chaptersdetails = json_decode($chaptersdetails);
        $data = new stdClass();
        $data->chapterids = $chapterids;
        $data->giportfolioid = $giportfolioid;
        $data->cm = $cm;
        $data->chapterandcoursemodule = $chapterandcoursemodule;
        $data->chaptersdetails = $chaptersdetails;

        $result = toolgiportfolio_importhtml_add_chapters_to_portfolio($data);

        return array(
            'status' => $result,
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the group
     * @return external_single_structure
     *
     */
    public static function import_chapters_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'file id'),
            )
        );
    }
}
