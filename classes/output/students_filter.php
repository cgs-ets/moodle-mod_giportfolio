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
 * Class for rendering student filters on the giportfolio reports page.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio\output;

use renderer_base;
use stdClass;

class students_filter extends \core\output\datafilter {

    /** @var int $giportfolioid The giportfolio instance id. */
    protected $giportfolioid;

    /** @var int $cmid The course module id. */
    protected $cmid;

    /** @var \stdClass $giportfolio The giportfolio instance record. */
    protected $giportfolio;

    /**
     * Constructor.
     *
     * @param \context $context The context where the filters are being rendered.
     * @param string|null $tableregionid Container of the table to be updated by this filter.
     * @param int $giportfolioid The giportfolio instance id.
     * @param int $cmid The course module id.
     */
    public function __construct(\context $context, ?string $tableregionid, int $giportfolioid, int $cmid) {
        global $DB;
        parent::__construct($context, $tableregionid);
        $this->giportfolioid = $giportfolioid;
        $this->cmid = $cmid;
        $this->giportfolio = $DB->get_record('giportfolio', ['id' => $giportfolioid], '*', MUST_EXIST);
    }

    /**
     * Get data for all filter types.
     *
     * @return array
     */
    protected function get_filtertypes(): array {
        $filtertypes = [];

        if ($filtertype = $this->get_groups_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($filtertype = $this->get_chapter_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($filtertype = $this->get_status_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($this->giportfolio->numberhours) {
            if ($filtertype = $this->get_tutor_filter()) {
                $filtertypes[] = $filtertype;
            }
        }

        return $filtertypes;
    }

    /**
     * Get data for the groups filter.
     *
     * @return stdClass|null
     */
    protected function get_groups_filter(): ?stdClass {
        global $USER;

        $coursecontext = $this->context;
        // If context is module level, get course context.
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }

        $course = get_course($coursecontext->instanceid);
        $seeallgroups = has_capability('moodle/site:accessallgroups', $coursecontext);
        $seeallgroups = $seeallgroups || ($course->groupmode != SEPARATEGROUPS);

        if ($seeallgroups) {
            $groups = groups_get_all_groups($course->id);
        } else {
            $groups = groups_get_all_groups($course->id, $USER->id);
        }

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
     * Get data for the chapter filter.
     *
     * @return stdClass|null
     */
    protected function get_chapter_filter(): ?stdClass {
        global $DB;

        $chapters = $DB->get_records('giportfolio_chapters', [
            'giportfolioid' => $this->giportfolioid,
            'userid' => 0,
            'hidden' => 0,
        ], 'pagenum ASC', 'id, title');

        if (empty($chapters)) {
            return null;
        }

        return $this->get_filter_object(
            'chapter',
            get_string('chapters', 'mod_giportfolio'),
            false,
            true,
            null,
            array_map(function($chapter) {
                return (object) [
                    'value' => $chapter->id,
                    'title' => format_string($chapter->title),
                ];
            }, array_values($chapters))
        );
    }

    /**
     * Get data for the contribution status filter.
     *
     * @return stdClass|null
     */
    protected function get_status_filter(): ?stdClass {
        return $this->get_filter_object(
            'status',
            get_string('contributions', 'mod_giportfolio'),
            false,
            false,
            null,
            [
                (object) [
                    'value' => 1,
                    'title' => get_string('hascontributions', 'mod_giportfolio'),
                ],
                (object) [
                    'value' => 0,
                    'title' => get_string('nocontributions', 'mod_giportfolio'),
                ],
            ]
        );
    }

    /**
     * Get data for the tutor filter.
     *
     * Tutors are non-student members of course groups.
     *
     * @return stdClass|null
     */
    protected function get_tutor_filter(): ?stdClass {
        $coursecontext = $this->context;
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }
        $course = get_course($coursecontext->instanceid);
        $groups = groups_get_all_groups($course->id, 0, 0, 'g.*', true);

        if (empty($groups)) {
            return null;
        }

        // Collect unique non-student members across all groups.
        // A tutor is anyone enrolled in the course whose role(s) in this course are NOT student.
        $studentroleids = array_keys(get_archetype_roles('student'));
        $tutors = [];
        foreach ($groups as $group) {
            foreach ($group->members as $memberid => $unused) {
                if (isset($tutors[$memberid])) {
                    continue;
                }
                if (!self::user_is_student_in_course($memberid, $coursecontext->id, $studentroleids)) {
                    $user = \core_user::get_user($memberid, 'id, firstname, lastname');
                    if ($user) {
                        $tutors[$memberid] = fullname($user);
                    }
                }
            }
        }

        if (empty($tutors)) {
            return null;
        }

        $values = [];
        foreach ($tutors as $id => $name) {
            $values[] = (object) [
                'value' => $id,
                'title' => $name,
            ];
        }

        return $this->get_filter_object(
            'tutor',
            get_string('tutor', 'mod_giportfolio'),
            false,
            true,
            null,
            $values
        );
    }

    /**
     * Export the renderer data in a mustache template friendly format.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        $coursecontext = $this->context;
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }

        return (object) [
            'tableregionid' => $this->tableregionid,
            'courseid' => $coursecontext->instanceid,
            'cmid' => $this->cmid,
            'giportfolioid' => $this->giportfolioid,
            'filtertypes' => $this->get_filtertypes(),
            'rownumber' => 1,
        ];
    }

    /**
     * Check whether a user has a student role assigned in the given course context.
     *
     * Only checks role_assignments in the exact course context (no inheritance).
     *
     * @param int $userid The user id.
     * @param int $coursecontextid The course context id.
     * @param int[] $studentroleids Role ids with student archetype.
     * @return bool True if the user has a student role in this course.
     */
    public static function user_is_student_in_course(int $userid, int $coursecontextid, array $studentroleids): bool {
        global $DB;
        if (empty($studentroleids)) {
            return false;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($studentroleids, SQL_PARAMS_NAMED, 'sr');
        $inparams['userid'] = $userid;
        $inparams['ctxid'] = $coursecontextid;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} WHERE userid = :userid AND contextid = :ctxid AND roleid {$insql}",
            $inparams
        );
    }
}
