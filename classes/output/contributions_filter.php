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
 * Renderable for the contribution report filter UI (year selector + groups filter).
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio\output;

use renderer_base;
use stdClass;

class contributions_filter extends \core\output\datafilter {

    /** @var int */
    protected $giportfolioid;

    /** @var int */
    protected $cmid;

    /** @var int Selected year. */
    protected $selectedyear;

    /**
     * Constructor.
     *
     * @param \context $context
     * @param string|null $tableregionid
     * @param int $giportfolioid
     * @param int $cmid
     * @param int $selectedyear
     */
    public function __construct(\context $context, ?string $tableregionid,
            int $giportfolioid, int $cmid, int $selectedyear) {
        parent::__construct($context, $tableregionid);
        $this->giportfolioid = $giportfolioid;
        $this->cmid          = $cmid;
        $this->selectedyear  = $selectedyear;
    }

    /**
     * Get filter types — only groups filter for this report.
     *
     * @return array
     */
    protected function get_filtertypes(): array {
        $filtertypes = [];

        if ($filtertype = $this->get_groups_filter()) {
            $filtertypes[] = $filtertype;
        }

        return $filtertypes;
    }

    /**
     * Groups filter — same logic as students_filter.php.
     *
     * @return stdClass|null
     */
    protected function get_groups_filter(): ?stdClass {
        global $USER;

        $coursecontext = $this->context;
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }

        $course      = get_course($coursecontext->instanceid);
        $seeallgroups = has_capability('moodle/site:accessallgroups', $coursecontext);
        $seeallgroups = $seeallgroups || ($course->groupmode != SEPARATEGROUPS);

        $groups = $seeallgroups
            ? groups_get_all_groups($course->id)
            : groups_get_all_groups($course->id, $USER->id);

        if (empty($groups)) {
            return null;
        }

        return $this->get_filter_object(
            'groups',
            get_string('groups'),
            false,
            true,
            null,
            array_map(function($group) {
                return (object) [
                    'value' => $group->id,
                    'title' => format_string($group->name, true, ['context' => $this->context]),
                ];
            }, array_values($groups))
        );
    }

    /**
     * Return distinct years from template chapters for this portfolio.
     * Uses Unix timestamp range — DB-agnostic.
     *
     * @return array Array of ints (years), descending.
     */
    public function get_available_years(): array {
        global $DB;

        $chapters = $DB->get_records_select(
            'giportfolio_chapters',
            'giportfolioid = :gid AND userid = 0',
            ['gid' => $this->giportfolioid],
            '',
            'id, timecreated'
        );

        $years = [];
        foreach ($chapters as $ch) {
            $yr = (int) date('Y', $ch->timecreated);
            $years[$yr] = $yr;
        }

        krsort($years);

        return array_values($years);
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $coursecontext = $this->context;
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }

        $availableyears = $this->get_available_years();

        // If selectedyear is not in the list (e.g. no chapters this year), use the first available.
        if (!in_array($this->selectedyear, $availableyears) && !empty($availableyears)) {
            $this->selectedyear = $availableyears[0];
        }

        $years = array_map(function(int $yr) {
            return [
                'value'    => $yr,
                'title'    => (string) $yr,
                'selected' => $yr === $this->selectedyear,
            ];
        }, $availableyears);

        return (object) [
            'tableregionid' => $this->tableregionid,
            'courseid'      => $coursecontext->instanceid,
            'cmid'          => $this->cmid,
            'giportfolioid' => $this->giportfolioid,
            'selectedyear'  => $this->selectedyear,
            'years'         => $years,
            'filtertypes'   => $this->get_filtertypes(),
            'rownumber'     => 1,
        ];
    }
}
