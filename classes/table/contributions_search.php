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
 * Class used to fetch enrolled staff for the contribution report.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_giportfolio\table;

use core_table\local\filter\filterset;
use context;
use stdClass;

class contributions_search {

    /** @var filterset $filterset */
    protected $filterset;

    /** @var stdClass $course */
    protected $course;

    /** @var context $context */
    protected $context;

    /** @var int $cmid */
    protected $cmid;

    /** @var int $giportfolioid */
    protected $giportfolioid;

    public function __construct(stdClass $course, context $context, filterset $filterset, int $cmid, int $giportfolioid) {
        $this->course        = $course;
        $this->context       = $context;
        $this->filterset     = $filterset;
        $this->cmid          = $cmid;
        $this->giportfolioid = $giportfolioid;
    }

    /**
     * Fetch enrolled staff matching the filterset.
     *
     * @param string $additionalwhere
     * @param array  $additionalparams
     * @param string $sort
     * @param int    $limitfrom
     * @param int    $limitnum
     * @return \moodle_recordset
     */
    public function get_participants(string $additionalwhere = '', array $additionalparams = [],
            string $sort = '', int $limitfrom = 0, int $limitnum = 0): \moodle_recordset {
        global $DB;

        [
            'subqueryalias' => $subqueryalias,
            'outerselect'   => $outerselect,
            'innerselect'   => $innerselect,
            'outerjoins'    => $outerjoins,
            'innerjoins'    => $innerjoins,
            'outerwhere'    => $outerwhere,
            'innerwhere'    => $innerwhere,
            'params'        => $params,
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
     * Build the SQL for enrolled staff with supervisor name.
     */
    protected function get_participants_sql(string $additionalwhere, array $additionalparams): array {
        global $CFG, $DB;

        $usersubqueryalias = 'targetusers';
        $inneruseralias    = 'udistinct';

        $innerselect = "SELECT DISTINCT {$inneruseralias}.id";
        $innerjoins  = ["{user} {$inneruseralias}"];
        $innerwhere  = "WHERE {$inneruseralias}.deleted = 0 AND {$inneruseralias}.id <> :siteguest";
        $params      = ['siteguest' => $CFG->siteguest];

        $outerjoins = ["JOIN {user} u ON u.id = {$usersubqueryalias}.id"];
        $wheres     = [];

        // Enrolled staff who have the printclassplan capability.
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($this->context, 'mod/giportfolio:printclassplan');
        $innerjoins[] = "JOIN ({$enrolledsql}) je ON je.id = {$inneruseralias}.id";
        $params = array_merge($params, $enrolledparams);

        // User identity fields.
        $userfields = \core_user\fields::for_identity(null)->with_userpic();
        ['selects' => $userfieldssql, 'joins' => $userfieldsjoin, 'params' => $userfieldsparams] =
                (array)$userfields->get_sql('u', true);
        if ($userfieldsjoin) {
            $outerjoins[] = $userfieldsjoin;
            $params = array_merge($params, $userfieldsparams);
        }

        $outerselect = "SELECT COALESCE(ul.timeaccess, 0) AS lastaccess {$userfieldssql}";
        $outerjoins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid2)';
        $params['courseid2'] = $this->course->id;

        // Supervisor subquery — two-step fallback:
        // 1. Member of the same group with non-editing teacher role in the module context (portfolio-specific).
        // 2. If none found, fall back to any non-student member of the same group (course-level).
        // Use shortname 'teacher' = non-editing teacher role (archetype 'teacher').
        // get_archetype_roles covers renamed roles like "Presenter (Non-editing teacher)".
        $supervisorroleids = array_keys(get_archetype_roles('teacher'));
        $studentroleids    = array_keys(get_archetype_roles('student'));
        $modulecontextid   = $this->context->id;
        $coursecontext     = \context_course::instance($this->course->id);
        if (!empty($supervisorroleids) && !empty($studentroleids)) {
            [$supinsql, $supinparams]   = $DB->get_in_or_equal($supervisorroleids, SQL_PARAMS_NAMED, 'suprl');
            [$stuinsql, $stuinparams]   = $DB->get_in_or_equal($studentroleids, SQL_PARAMS_NAMED, 'sturl');
            $outerselect .= ", COALESCE(supervisorsub.supervisorname, '') AS supervisorname";
            $outerjoins[] = "LEFT JOIN (
                SELECT gm_s.userid AS studentid,
                       COALESCE(
                           MIN(CASE WHEN EXISTS (
                               SELECT 1 FROM {role_assignments} ra_mod
                                WHERE ra_mod.userid = tu.id
                                  AND ra_mod.contextid = :supervisormodctxid
                                  AND ra_mod.roleid {$supinsql}
                           ) THEN CONCAT(tu.lastname, ' ', tu.firstname) END),
                           MIN(CASE WHEN NOT EXISTS (
                               SELECT 1 FROM {role_assignments} ra_crs
                                WHERE ra_crs.userid = tu.id
                                  AND ra_crs.contextid = :supervisorcoursectxid
                                  AND ra_crs.roleid {$stuinsql}
                           ) THEN CONCAT(tu.lastname, ' ', tu.firstname) END)
                       ) AS supervisorname
                  FROM {groups_members} gm_s
                  JOIN {groups} g_t ON g_t.id = gm_s.groupid AND g_t.courseid = :supervisorcourse
                  JOIN {groups_members} gm_t ON gm_t.groupid = gm_s.groupid AND gm_t.userid <> gm_s.userid
                  JOIN {user} tu ON tu.id = gm_t.userid
              GROUP BY gm_s.userid
            ) supervisorsub ON supervisorsub.studentid = u.id";
            $params['supervisormodctxid']    = $modulecontextid;
            $params['supervisorcoursectxid'] = $coursecontext->id;
            $params['supervisorcourse']      = $this->course->id;
            $params = array_merge($params, $supinparams, $stuinparams);
        } else {
            $outerselect .= ", '' AS supervisorname";
        }

        // Context preload.
        $ccselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin   = 'LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)';
        $params['contextlevel'] = CONTEXT_USER;
        $outerselect .= $ccselect;
        $outerjoins[] = $ccjoin;

        // Groups filter.
        if ($this->filterset->has_filter('groups')) {
            ['where' => $groupswhere, 'params' => $groupsparams] = $this->get_groups_sql();
            if (!empty($groupswhere)) {
                $wheres[] = "({$groupswhere})";
            }
            if (!empty($groupsparams)) {
                $params = array_merge($params, $groupsparams);
            }
        }

        if (!empty($additionalwhere)) {
            $innerwhere .= " AND ({$additionalwhere})";
            $params = array_merge($params, $additionalparams);
        }

        $outerjoinsstring = implode("\n", $outerjoins);
        $innerjoinsstring = implode("\n", $innerjoins);

        if ($wheres) {
            switch ($this->filterset->get_join_type()) {
                case $this->filterset::JOINTYPE_ALL:
                    $outerwhere = 'WHERE ' . implode(' AND ', $wheres);
                    break;
                case $this->filterset::JOINTYPE_NONE:
                    $outerwhere = 'WHERE NOT ' . implode(' AND NOT ', array_map(fn($w) => "({$w})", $wheres));
                    break;
                default:
                    $outerwhere = 'WHERE ' . implode(' OR ', $wheres);
                    break;
            }
        } else {
            $outerwhere = '';
        }

        return [
            'subqueryalias' => $usersubqueryalias,
            'outerselect'   => $outerselect,
            'innerselect'   => $innerselect,
            'outerjoins'    => $outerjoinsstring,
            'innerjoins'    => $innerjoinsstring,
            'outerwhere'    => $outerwhere,
            'innerwhere'    => $innerwhere,
            'params'        => $params,
        ];
    }

    /**
     * Get SQL for the groups filter.
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_groups_sql(): array {
        global $DB;

        $groupsfilter = $this->filterset->get_filter('groups');
        $groupids = [];
        foreach ($groupsfilter as $groupid) {
            $groupids[] = $groupid;
        }

        if (empty($groupids)) {
            return ['where' => '', 'params' => []];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');

        $where = "u.id IN (
            SELECT gm.userid
              FROM {groups_members} gm
             WHERE gm.groupid {$insql}
        )";

        return ['where' => $where, 'params' => $inparams];
    }
}
