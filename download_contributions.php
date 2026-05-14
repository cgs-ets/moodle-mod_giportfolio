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
 * Download contribution report data for a giportfolio instance.
 *
 * Accepts POST to avoid URI length limits when many users are selected.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $DB;

$id         = required_param('id', PARAM_INT);
$dataformat = required_param('dataformat', PARAM_ALPHA);
$year       = required_param('year', PARAM_INT);
$userids    = optional_param_array('userid', [], PARAM_INT);

$cm          = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$course      = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', ['id' => $cm->instance], '*', MUST_EXIST);
$context     = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/giportfolio:viewgiportfolios', $context);

if (empty($userids)) {
    $returnurl = new moodle_url('/mod/giportfolio/submissions.php', [
        'id'        => $cm->id,
        'tab'       => 'reports',
        'subreport' => 'contributions',
    ]);
    redirect($returnurl, get_string('nousersselected', 'mod_giportfolio'));
}

// Validate dataformat plugin.
$plugins = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
if (!isset($plugins[$dataformat]) || !$plugins[$dataformat]->is_enabled()) {
    throw new moodle_exception('invalidparam', 'error', '', 'dataformat');
}

// Load template chapters for the year using Unix timestamp range — DB-agnostic.
$yearstart = mktime(0, 0, 0, 1, 1, $year);
$yearend   = mktime(0, 0, 0, 1, 1, $year + 1);

$chapters = $DB->get_records_select(
    'giportfolio_chapters',
    'giportfolioid = :gid AND userid = 0
     AND timecreated >= :yearstart AND timecreated < :yearend',
    ['gid' => $giportfolio->id, 'yearstart' => $yearstart, 'yearend' => $yearend],
    'pagenum ASC',
    'id, title'
);

// Build column names: fixed columns first, then one per chapter.
$columnnames = [
    'firstname'  => get_string('firstname'),
    'lastname'   => get_string('lastname'),
    'email'      => get_string('email'),
    'supervisor' => get_string('supervisor', 'mod_giportfolio'),
];
foreach ($chapters as $chapter) {
    $columnnames['chapter_' . $chapter->id] = format_string($chapter->title);
}

// Build the contribmap for selected users (batch query — DB-agnostic).
$contribmap = [];
if (!empty($chapters)) {
    $chapterids = array_keys($chapters);
    [$chapinsql, $chapparams] = $DB->get_in_or_equal($chapterids, SQL_PARAMS_NAMED, 'dlchap');
    $chapparams['dlgid'] = $giportfolio->id;
    $rows = $DB->get_records_sql(
        "SELECT id, userid, chapterid
           FROM {giportfolio_contributions}
          WHERE giportfolioid = :dlgid AND chapterid {$chapinsql}",
        $chapparams
    );
    foreach ($rows as $row) {
        $contribmap[$row->userid][$row->chapterid] = true;
    }
}

// Pre-compute supervisor names — two-step fallback, fully DB-agnostic:
// 1. Non-editing teacher role in module context (portfolio-specific).
// 2. Fall back to any non-student member of the same group (course-level).
$supervisormap     = [];
$supervisorroleids = array_keys(get_archetype_roles('teacher'));
$studentroleids    = array_keys(get_archetype_roles('student'));
$coursecontext     = context_course::instance($course->id);
if (!empty($supervisorroleids) && !empty($studentroleids)) {
    [$supinsql, $supinparams] = $DB->get_in_or_equal($supervisorroleids, SQL_PARAMS_NAMED, 'dlsuprl');
    [$stuinsql, $stuinparams] = $DB->get_in_or_equal($studentroleids, SQL_PARAMS_NAMED, 'dlsturl');
    $supervisors = $DB->get_records_sql(
        "SELECT gm_s.userid AS studentid,
                COALESCE(
                    MIN(CASE WHEN ra_mod.id IS NOT NULL
                        THEN " . $DB->sql_concat('tu.lastname', "' '", 'tu.firstname') . " END),
                    MIN(CASE WHEN ra_crs.id IS NULL
                        THEN " . $DB->sql_concat('tu.lastname', "' '", 'tu.firstname') . " END)
                ) AS supervisorname
           FROM {groups_members} gm_s
           JOIN {groups} g ON g.id = gm_s.groupid AND g.courseid = :dlcid
           JOIN {groups_members} gm_t ON gm_t.groupid = gm_s.groupid AND gm_t.userid <> gm_s.userid
           JOIN {user} tu ON tu.id = gm_t.userid
      LEFT JOIN {role_assignments} ra_mod
             ON ra_mod.userid = tu.id
            AND ra_mod.contextid = :dlmodctxid
            AND ra_mod.roleid {$supinsql}
      LEFT JOIN {role_assignments} ra_crs
             ON ra_crs.userid = tu.id
            AND ra_crs.contextid = :dlcoursectxid
            AND ra_crs.roleid {$stuinsql}
       GROUP BY gm_s.userid",
        array_merge(
            ['dlcid' => $course->id, 'dlmodctxid' => $context->id, 'dlcoursectxid' => $coursecontext->id],
            $supinparams,
            $stuinparams
        )
    );
    foreach ($supervisors as $s) {
        $supervisormap[$s->studentid] = $s->supervisorname;
    }
}

// Fetch the selected users.
[$userinsql, $userinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'dluid');
$users = $DB->get_records_sql(
    "SELECT u.id, u.firstname, u.lastname, u.email
       FROM {user} u
      WHERE u.id {$userinsql}
   ORDER BY u.lastname, u.firstname",
    $userinparams
);

// Stream the download.
\core\dataformat::download_data(
    str_replace(' ', '_', trim($giportfolio->name)) . '_contributions_' . $year,
    $dataformat,
    $columnnames,
    $users,
    function (stdClass $record) use ($chapters, $contribmap, $supervisormap): stdClass {
        $out = new stdClass();
        $out->firstname  = $record->firstname;
        $out->lastname   = $record->lastname;
        $out->email      = $record->email;
        $out->supervisor = $supervisormap[$record->id] ?? '';

        foreach ($chapters as $chapter) {
            $key         = 'chapter_' . $chapter->id;
            $out->$key   = isset($contribmap[$record->id][$chapter->id])
                ? get_string('contributed', 'mod_giportfolio')
                : get_string('notcontributed', 'mod_giportfolio');
        }

        return $out;
    }
);
