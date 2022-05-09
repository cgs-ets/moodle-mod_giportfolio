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
 * @package   booktool_importhtml
 * @category    external
 * @copyright 2022 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$functions = [
    'giportfoliotool_import_chapter' => [
        'classname' => 'giportfoliotool_external', // Class containing a reference to the external function.
        'methodname' => 'import_chapters', // External function name.
        'description' => 'Saves the chapters dropped in the import area.', // Human readable description of the WS function.
        'type' => 'write', // DB rights of the WS function.
        'loginrequired' => true,
        'ajax' => true    // Is this service available to 'internal' ajax calls.
    ]
];
