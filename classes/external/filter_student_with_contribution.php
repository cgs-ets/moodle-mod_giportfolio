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
 *  External Web Service Template
 *
 * @package   mod_giportfolio
 * @copyright 2022 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio\external;

defined('MOODLE_INTERNAL') || die();

use external_function_parameters;
use external_value;
use external_single_structure;
use stdClass;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/giportfolio/lib.php');
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');

/**
 * Trait implementing the external function mod_giportfolio_filter_student_with_contribution
 */
trait filter_student_with_contribution {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */

    public static function filter_student_with_contribution_parameters() {
        return new external_function_parameters(
            array(
                'chapterid' => new external_value(PARAM_RAW, 'chapter to filter'),
                'giportfolioid' => new external_value(PARAM_RAW, 'giportfolio id the chapter belongs to'),

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
    public static function filter_student_with_contribution($chapterid, $giportfolioid) {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::filter_student_with_contribution_parameters(),
            array(
                'chapterid' => $chapterid,
                'giportfolioid' => $giportfolioid
            )
        );
        
        $data = new stdClass();
        $data->chapterid = $chapterid;
        $data->giportfolioid = $giportfolioid;
        $result = json_encode(filter_student_with_contribution($data));

        return array(
            'studentswithcontributions' => $result,

        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the group
     * @return external_single_structure
     *
     */
    public static function filter_student_with_contribution_returns() {
        return new external_single_structure(
            array(
                'studentswithcontributions' => new external_value(PARAM_RAW, 'Id of the students who made at least one contribution'),
            )
        );
    }
}
