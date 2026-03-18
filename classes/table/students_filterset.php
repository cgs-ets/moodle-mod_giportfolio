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
 * Students table filterset.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_giportfolio\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;

class students_filterset extends filterset {

    /**
     * Get the required filters.
     *
     * The courseid and cmid are required to scope the query.
     *
     * @return array
     */
    public function get_required_filters(): array {
        return [
            'courseid' => integer_filter::class,
            'cmid' => integer_filter::class,
            'giportfolioid' => integer_filter::class,
        ];
    }

    /**
     * Get the optional filters.
     *
     * - groups: filter by course groups.
     * - chapter: filter by portfolio chapter.
     * - status: filter by contribution status (has contributions or not).
     *
     * @return array
     */
    public function get_optional_filters(): array {
        return [
            'groups' => integer_filter::class,
            'chapter' => integer_filter::class,
            'status' => integer_filter::class,
            'tutor' => integer_filter::class,
        ];
    }
}
