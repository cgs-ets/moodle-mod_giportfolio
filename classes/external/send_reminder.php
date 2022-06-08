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
 * Trait implementing the external function mod_giportfolio_send_reminder
 */
trait send_reminder {


    /**
     * Returns description of method parameters
     * @return external_function_parameters
     *
     */

    public static function send_reminder_parameters() {
        return new external_function_parameters(
            array(
                'users' => new external_value(PARAM_RAW, 'list of user uds'),
                'chapter' => new external_value(PARAM_RAW, 'chapter to remind'),
                'textmsg' => new external_value(PARAM_RAW, 'message to send'),
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
    public static function send_reminder($users, $chapter, $textmsg) {
        global $COURSE, $DB;

        $context = \context_course::instance($COURSE->id);
        self::validate_context($context);

        // Parameters validation.
        self::validate_parameters(
            self::send_reminder_parameters(),
            array(
                'users' => $users,
                'chapter' => $chapter,
                'textmsg' => $textmsg
            )
        );

        $data = new stdClass();
        $data->users = json_decode($users);
        $data->chapter = json_decode($chapter);
        $data->textmsg = $textmsg;

        giportfolio_send_reminder($data);
      

        return array(
            'status' => '<h1>' . get_string('msg_sent', 'giportfolio') . '</h1>',
            'date' => userdate(time(), get_string('strftimedaydate', 'core_langconfig'))
        );
    }

    /**
     * Describes the structure of the function return value.
     * Returns the URL of the file for the group
     * @return external_single_structure
     *
     */
    public static function send_reminder_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'file id'),
                'date' => new external_value(PARAM_RAW, 'date it was created'),
            )
        );
    }
}
