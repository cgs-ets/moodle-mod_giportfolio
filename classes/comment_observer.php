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
 * Comment observer.
 *
 * @package    mod_giportfolio
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/giportfolio/locallib.php');

/**
 * Comment observers class.
 *
 * @package    mod_giportfolio
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment_observer {


    /**
     *  A comment has been made. Send notification
     *
     * @param  $event The event.
     * @return void
     */
    public static function send_notification($event) {

        $gcid = ($event->other)['itemid']; // Contribution id.

        giportfolio_send_comment_notification($event->userid, $gcid);
    }
}
