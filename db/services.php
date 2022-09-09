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
 * Plugin external functions and services are defined here.
 *
 * @package   mod_giportfolio
 * @category    external
 * @copyright 2022 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_giportfolio_send_reminder' => [
        'classname' => 'mod_giportfolio\external\api', // Class containing a reference to the external function.
        'methodname' => 'send_reminder', // External function name.
        'description' => 'Send a reminder to a list of users', // Human readable description of the WS function.
        'type' => 'read', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_giportfolio_lock_chapter' => [
        'classname' => 'mod_giportfolio\external\api', // Class containing a reference to the external function.
        'methodname' => 'lock_chapter', // External function name.
        'description' => 'Locks a chapter', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_giportfolio_unlock_chapter' => [
        'classname' => 'mod_giportfolio\external\api', // Class containing a reference to the external function.
        'methodname' => 'unlock_chapter', // External function name.
        'description' => 'Unocks a chapter', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ],
    'mod_giportfolio_filter_student_with_contribution' => [
        'classname' => 'mod_giportfolio\external\api', // Class containing a reference to the external function.
        'methodname' => 'filter_student_with_contribution', // External function name.
        'description' => 'Filter students with contributions in the chapter clicked', // Human readable description of the WS function.
        'type' => 'read', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ]

];
