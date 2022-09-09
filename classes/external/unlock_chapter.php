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

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/giportfolio/lib.php');
require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');

/**
 * Trait implementing the external function mod_giportfolio_lock_chapter
 */
trait unlock_chapter {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */

    public static function unlock_chapter_parameters() {
        return new external_function_parameters(
            array(
                'chapter' => new external_value(PARAM_RAW, 'chapter to unlock'),
               
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
    public static function unlock_chapter($chapter) {
        global $COURSE;

        $context = \context_course::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::unlock_chapter_parameters(),
            array(
                'chapter' => $chapter,
            )
        );

        $chapterid = giportfolio_unlock_chapter(json_decode($chapter));

        return array(
            'chapterid' => $chapterid,
            
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the group
     * @return external_single_structure
     *
     */
    public static function unlock_chapter_returns() {
        return new external_single_structure(
            array(
                'chapterid' => new external_value(PARAM_RAW, 'Chapter updated id'),
            )
        );
    }
}
