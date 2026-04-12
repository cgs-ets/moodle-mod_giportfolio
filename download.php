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
 * Download students report data for a giportfolio instance.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$dataformat = required_param('dataformat', PARAM_ALPHA);
$userids = required_param_array('userid', PARAM_INT);

$cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$giportfolio = $DB->get_record('giportfolio', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/giportfolio:viewgiportfolios', $context);

if (empty($userids)) {
    $returnurl = new moodle_url('/mod/giportfolio/submissions.php', ['id' => $cm->id, 'tab' => 'reports']);
    redirect($returnurl, get_string('nousersselected', 'mod_giportfolio'));
}

// Validate dataformat plugin.
$plugins = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
if (!isset($plugins[$dataformat]) || !$plugins[$dataformat]->is_enabled()) {
    throw new moodle_exception('invalidparam', 'error', '', 'dataformat');
}

// Build column names.
$columnnames = [
    'firstname' => get_string('firstname'),
    'lastname' => get_string('lastname'),
    'email' => get_string('email'),
    'groups' => get_string('groups'),
];

$columnnames['tutor'] = get_string('tutor', 'mod_giportfolio');

$columnnames['contributions'] = get_string('contributions', 'mod_giportfolio');
$columnnames['lastaccess'] = get_string('lastcourseaccess');

// Build query.
[$enrolledsql, $enrolledparams] = get_enrolled_sql($context, 'mod/giportfolio:printclassplan');
[$useridsql, $useridparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

[$groupconcatnamesql, $groupconcatnameparams] = groups_get_names_concat_sql($course->id);

$sql = "SELECT u.id, u.firstname, u.lastname, u.email,
               COALESCE(gcn.groupnames, '') AS groups,
               COALESCE(ul.timeaccess, 0) AS lastaccess,
               COALESCE(gc.contribcount, 0) AS contributions
          FROM {user} u
          JOIN ({$enrolledsql}) je ON je.id = u.id
     LEFT JOIN ({$groupconcatnamesql}) gcn ON gcn.userid = u.id
     LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = :courseid
     LEFT JOIN (
                SELECT userid, COUNT(*) AS contribcount
                  FROM {giportfolio_contributions}
                 WHERE giportfolioid = :giportfolioid
              GROUP BY userid
               ) gc ON gc.userid = u.id
         WHERE u.id {$useridsql}
      ORDER BY u.lastname, u.firstname";

$params = array_merge(
    $enrolledparams,
    $groupconcatnameparams,
    ['courseid' => $course->id, 'giportfolioid' => $giportfolio->id],
    $useridparams
);

// Pre-compute tutor names per group if numberhours is enabled.
$grouptutors = [];
$coursecontext = context_course::instance($course->id);
$studentroleids = array_keys(get_archetype_roles('student'));
$coursegroups = groups_get_all_groups($course->id, 0, 0, 'g.*', true);
foreach ($coursegroups as $group) {
    $tutornames = [];
    foreach ($group->members as $memberid => $unused) {
        if (!\mod_giportfolio\output\students_filter::user_is_student_in_course(
                $memberid, $coursecontext->id, $studentroleids)) {
            $user = $DB->get_record('user', ['id' => $memberid], 'id, firstname, lastname');
            if ($user) {
                $tutornames[] = fullname($user);
            }
        }
    }
    $grouptutors[$group->id] = $tutornames;
}

$rs = $DB->get_recordset_sql($sql, $params);

\core\dataformat::download_data(
    'giportfolio_' . $giportfolio->id . '_students',
    $dataformat,
    $columnnames,
    $rs,
    function (stdClass $record, bool $supportshtml) use ($giportfolio, $course, $grouptutors, $columnnames): stdClass {
        // Compute tutor value if needed.
        $usergroups = groups_get_all_groups($course->id, $record->id);
        $tutors = [];
        foreach ($usergroups as $ug) {
            if (isset($grouptutors[$ug->id])) {
                foreach ($grouptutors[$ug->id] as $name) {
                    $tutors[$name] = $name;
                }
            }
        }
        $tutorvalue = implode(', ', $tutors);

        // Format last access.
        $lastaccess = $record->lastaccess
            ? userdate($record->lastaccess)
            : get_string('never');

        // Format contributions.
        $count = (int) $record->contributions;
        $contributions = $count > 0
            ? get_string('hascontributions', 'mod_giportfolio')
            : get_string('nocontributions', 'mod_giportfolio');

        // Rebuild record in the exact column order.
        $out = new stdClass();
        foreach ($columnnames as $key => $label) {
            switch ($key) {
                case 'tutor':
                    $out->tutor = $tutorvalue;
                    break;
                case 'lastaccess':
                    $out->lastaccess = $lastaccess;
                    break;
                case 'contributions':
                    $out->contributions = $contributions;
                    break;
                default:
                    $out->$key = $record->$key ?? '';
                    break;
            }
        }

        return $out;
    }
);

$rs->close();
