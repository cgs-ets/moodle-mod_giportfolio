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
 * Class used to fetch students based on a filterset for giportfolio reports.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio\table;

use core_table\local\filter\filterset;
use context;
use stdClass;

class students_search {

    /** @var filterset $filterset The filterset describing which students to include. */
    protected $filterset;

    /** @var stdClass $course The course being searched. */
    protected $course;

    /** @var context $context The context of the search. */
    protected $context;

    /** @var int $cmid The course module id for the giportfolio. */
    protected $cmid;

    /** @var int $giportfolioid The giportfolio instance id. */
    protected $giportfolioid;

    /**
     * Class constructor.
     *
     * @param stdClass $course The course being searched.
     * @param context $context The context of the search.
     * @param filterset $filterset The filterset used to filter the students.
     * @param int $cmid The course module id.
     * @param int $giportfolioid The giportfolio instance id.
     */
    public function __construct(stdClass $course, context $context, filterset $filterset, int $cmid, int $giportfolioid) {
        $this->course = $course;
        $this->context = $context;
        $this->filterset = $filterset;
        $this->cmid = $cmid;
        $this->giportfolioid = $giportfolioid;
    }

    /**
     * Fetch students matching the filterset.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params used by $additionalwhere.
     * @param string $sort Optional SQL sort.
     * @param int $limitfrom Return a subset of records, starting at this point.
     * @param int $limitnum Return a subset comprising this many records.
     * @return \moodle_recordset
     */
    public function get_participants(string $additionalwhere = '', array $additionalparams = [], string $sort = '',
            int $limitfrom = 0, int $limitnum = 0): \moodle_recordset {
        global $DB;

        [
            'subqueryalias' => $subqueryalias,
            'outerselect' => $outerselect,
            'innerselect' => $innerselect,
            'outerjoins' => $outerjoins,
            'innerjoins' => $innerjoins,
            'outerwhere' => $outerwhere,
            'innerwhere' => $innerwhere,
            'params' => $params,
        ] = $this->get_participants_sql($additionalwhere, $additionalparams);

        $select = "{$outerselect}
                        FROM ({$innerselect}
                                FROM {$innerjoins}
                              {$innerwhere}
                        ) {$subqueryalias}
                   {$outerjoins}
                   {$outerwhere}";

        return $DB->get_counted_recordset_sql(
            sql: $select,
            fullcountcolumn: 'fullcount',
            sort: $sort,
            params: $params,
            limitfrom: $limitfrom,
            limitnum: $limitnum,
        );
    }

    /**
     * Generate the SQL used to fetch filtered data for the students table.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params.
     * @return array
     */
    protected function get_participants_sql(string $additionalwhere, array $additionalparams): array {
        global $CFG;

        $usersubqueryalias = 'targetusers';
        $inneruseralias = 'udistinct';

        // Inner query: distinct enrolled users who are not deleted and not guest.
        $innerselect = "SELECT DISTINCT {$inneruseralias}.id";
        $innerjoins = ["{user} {$inneruseralias}"];
        $innerwhere = "WHERE {$inneruseralias}.deleted = 0 AND {$inneruseralias}.id <> :siteguest";
        $params = ['siteguest' => $CFG->siteguest];

        $outerjoins = ["JOIN {user} u ON u.id = {$usersubqueryalias}.id"];
        $wheres = [];

        // Only students: enrolled users who have the 'printclassplan' capability (student archetype only).
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($this->context, 'mod/giportfolio:printclassplan');
        $innerjoins[] = "JOIN ({$enrolledsql}) je ON je.id = {$inneruseralias}.id";
        $params = array_merge($params, $enrolledparams);

        // User fields for display.
        $userfields = \core_user\fields::for_identity(null)->with_userpic();
        ['selects' => $userfieldssql, 'joins' => $userfieldsjoin, 'params' => $userfieldsparams] =
                (array)$userfields->get_sql('u', true);
        if ($userfieldsjoin) {
            $outerjoins[] = $userfieldsjoin;
            $params = array_merge($params, $userfieldsparams);
        }

        // Last access to course.
        $outerselect = "SELECT COALESCE(ul.timeaccess, 0) AS lastaccess {$userfieldssql}";
        $outerjoins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid2)';
        $params['courseid2'] = $this->course->id;

        // Contribution count for status display.
        $outerselect .= ", COALESCE(gc.contribcount, 0) AS contribcount";
        $outerjoins[] = "LEFT JOIN (
                            SELECT userid, COUNT(*) AS contribcount
                              FROM {giportfolio_contributions}
                             WHERE giportfolioid = :giportfolioid1
                          GROUP BY userid
                         ) gc ON gc.userid = u.id";
        $params['giportfolioid1'] = $this->giportfolioid;

        // Tutor name for sorting (non-student member of the student's group in this course).
        $studentroleids = array_keys(get_archetype_roles('student'));
        if (!empty($studentroleids)) {
            global $DB;
            [$insql, $inparams] = $DB->get_in_or_equal($studentroleids, SQL_PARAMS_NAMED, 'strl');
            $coursecontext = \context_course::instance($this->course->id);
            $outerselect .= ", COALESCE(tutorsub.tutorname, '') AS tutorname";
            $outerjoins[] = "LEFT JOIN (
                SELECT gm_s.userid AS studentid,
                       MIN(CONCAT(tu.lastname, ' ', tu.firstname)) AS tutorname
                  FROM {groups_members} gm_s
                  JOIN {groups} g_t ON g_t.id = gm_s.groupid AND g_t.courseid = :tutorcourse
                  JOIN {groups_members} gm_t ON gm_t.groupid = gm_s.groupid AND gm_t.userid <> gm_s.userid
                  JOIN {user} tu ON tu.id = gm_t.userid
                 WHERE NOT EXISTS (
                       SELECT 1 FROM {role_assignments} ra_s
                        WHERE ra_s.userid = tu.id
                          AND ra_s.contextid = :tutorctxid
                          AND ra_s.roleid {$insql}
                   )
              GROUP BY gm_s.userid
            ) tutorsub ON tutorsub.studentid = u.id";
            $params['tutorcourse'] = $this->course->id;
            $params['tutorctxid'] = $coursecontext->id;
            $params = array_merge($params, $inparams);
        } else {
            $outerselect .= ", '' AS tutorname";
        }

        // Context preload.
        $ccselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = 'LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)';
        $params['contextlevel'] = CONTEXT_USER;
        $outerselect .= $ccselect;
        $outerjoins[] = $ccjoin;

        // Apply groups filter.
        if ($this->filterset->has_filter('groups')) {
            [
                'where' => $groupswhere,
                'params' => $groupsparams,
            ] = $this->get_groups_sql();

            if (!empty($groupswhere)) {
                $wheres[] = "({$groupswhere})";
            }
            if (!empty($groupsparams)) {
                $params = array_merge($params, $groupsparams);
            }
        }

        // Apply chapter filter.
        if ($this->filterset->has_filter('chapter')) {
            [
                'where' => $chapterwhere,
                'params' => $chapterparams,
            ] = $this->get_chapter_sql();

            if (!empty($chapterwhere)) {
                $wheres[] = "({$chapterwhere})";
            }
            if (!empty($chapterparams)) {
                $params = array_merge($params, $chapterparams);
            }
        }

        // Apply tutor filter (students whose group contains the selected tutor).
        if ($this->filterset->has_filter('tutor')) {
            [
                'where' => $tutorwhere,
                'params' => $tutorparams,
            ] = $this->get_tutor_sql();

            if (!empty($tutorwhere)) {
                $wheres[] = "({$tutorwhere})";
            }
            if (!empty($tutorparams)) {
                $params = array_merge($params, $tutorparams);
            }
        }

        // Apply status filter (has contributions / no contributions).
        if ($this->filterset->has_filter('status')) {
            [
                'where' => $statuswhere,
                'params' => $statusparams,
            ] = $this->get_status_sql();

            if (!empty($statuswhere)) {
                $wheres[] = "({$statuswhere})";
            }
            if (!empty($statusparams)) {
                $params = array_merge($params, $statusparams);
            }
        }

        // Add any supplied additional WHERE clauses.
        if (!empty($additionalwhere)) {
            $innerwhere .= " AND ({$additionalwhere})";
            $params = array_merge($params, $additionalparams);
        }

        // Prepare final values.
        $outerjoinsstring = implode("\n", $outerjoins);
        $innerjoinsstring = implode("\n", $innerjoins);
        if ($wheres) {
            switch ($this->filterset->get_join_type()) {
                case $this->filterset::JOINTYPE_ALL:
                    $wherenot = '';
                    $wheresjoin = ' AND ';
                    break;
                case $this->filterset::JOINTYPE_NONE:
                    $wherenot = ' NOT ';
                    $wheresjoin = ' AND NOT ';
                    $wheres = array_map(function($where) {
                        return "({$where})";
                    }, $wheres);
                    break;
                default:
                    $wherenot = '';
                    $wheresjoin = ' OR ';
                    break;
            }
            $outerwhere = 'WHERE ' . $wherenot . implode($wheresjoin, $wheres);
        } else {
            $outerwhere = '';
        }

        return [
            'subqueryalias' => $usersubqueryalias,
            'outerselect' => $outerselect,
            'innerselect' => $innerselect,
            'outerjoins' => $outerjoinsstring,
            'innerjoins' => $innerjoinsstring,
            'outerwhere' => $outerwhere,
            'innerwhere' => $innerwhere,
            'params' => $params,
        ];
    }

    /**
     * Get the SQL for filtering by groups.
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_groups_sql(): array {
        $groupsfilter = $this->filterset->get_filter('groups');
        $groupids = [];
        foreach ($groupsfilter as $groupid) {
            $groupids[] = $groupid;
        }

        if (empty($groupids)) {
            return ['where' => '', 'params' => []];
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');

        $where = "u.id IN (
            SELECT gm.userid
              FROM {groups_members} gm
             WHERE gm.groupid {$insql}
        )";

        return ['where' => $where, 'params' => $inparams];
    }

    /**
     * Get the SQL for filtering by chapter (users who contributed to a specific chapter).
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_chapter_sql(): array {
        $chapterfilter = $this->filterset->get_filter('chapter');
        $chapterids = [];
        foreach ($chapterfilter as $chapterid) {
            $chapterids[] = $chapterid;
        }

        if (empty($chapterids)) {
            return ['where' => '', 'params' => []];
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($chapterids, SQL_PARAMS_NAMED, 'chap');

        $where = "u.id IN (
            SELECT gc2.userid
              FROM {giportfolio_contributions} gc2
             WHERE gc2.giportfolioid = :giportfolioid_chap
               AND gc2.chapterid {$insql}
        )";
        $inparams['giportfolioid_chap'] = $this->giportfolioid;

        return ['where' => $where, 'params' => $inparams];
    }

    /**
     * Get the SQL for filtering by contribution status.
     *
     * Status values:
     * - 1 = Has contributions
     * - 0 = No contributions
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_status_sql(): array {
        $statusfilter = $this->filterset->get_filter('status');
        $statusvalues = [];
        foreach ($statusfilter as $status) {
            $statusvalues[] = $status;
        }

        if (empty($statusvalues)) {
            return ['where' => '', 'params' => []];
        }

        $conditions = [];
        $params = [];

        foreach ($statusvalues as $status) {
            if ($status == 1) {
                // Has contributions.
                $conditions[] = "u.id IN (
                    SELECT gc3.userid
                      FROM {giportfolio_contributions} gc3
                     WHERE gc3.giportfolioid = :giportfolioid_has
                )";
                $params['giportfolioid_has'] = $this->giportfolioid;
            } else {
                // No contributions.
                $conditions[] = "u.id NOT IN (
                    SELECT gc4.userid
                      FROM {giportfolio_contributions} gc4
                     WHERE gc4.giportfolioid = :giportfolioid_no
                )";
                $params['giportfolioid_no'] = $this->giportfolioid;
            }
        }

        $jointype = $statusfilter->get_join_type();
        if ($jointype === $statusfilter::JOINTYPE_ALL) {
            $where = implode(' AND ', $conditions);
        } else {
            $where = implode(' OR ', $conditions);
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Get the SQL for filtering by tutor.
     *
     * Shows students who belong to a group that also contains the selected tutor(s).
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_tutor_sql(): array {
        $tutorfilter = $this->filterset->get_filter('tutor');
        $tutorids = [];
        foreach ($tutorfilter as $tutorid) {
            $tutorids[] = $tutorid;
        }

        if (empty($tutorids)) {
            return ['where' => '', 'params' => []];
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($tutorids, SQL_PARAMS_NAMED, 'tutor');

        // Student must be in a course group where the selected tutor is also a member.
        $where = "u.id IN (
            SELECT gm1.userid
              FROM {groups_members} gm1
              JOIN {groups} g1 ON g1.id = gm1.groupid AND g1.courseid = :tutorcourseid
             WHERE gm1.groupid IN (
                SELECT gm2.groupid
                  FROM {groups_members} gm2
                 WHERE gm2.userid {$insql}
             )
        )";
        $inparams['tutorcourseid'] = $this->course->id;

        return ['where' => $where, 'params' => $inparams];
    }
}
