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
 * Contains the class used for displaying the giportfolio students table.
 *
 * @package    mod_giportfolio
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_giportfolio\table;

use context;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filterset;
use moodle_url;
use table_sql;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

class students extends table_sql implements dynamic_table {

    /** @var int $courseid The course id. */
    protected $courseid;

    /** @var filterset $filterset Filterset describing which students to include. */
    protected $filterset;

    /** @var moodle_url $baseurl The base URL for the report. */
    public $baseurl;

    /** @var \stdClass[] $groups The list of groups with membership info for the course. */
    protected $groups;

    /** @var \stdClass $course The course details. */
    protected $course;

    /** @var context $context The course module context. */
    protected $context;

    /** @var int $cmid The course module id. */
    protected $cmid;

    /** @var int $giportfolioid The giportfolio instance id. */
    protected $giportfolioid;

    /** @var \stdClass $giportfolio The giportfolio instance record. */
    protected $giportfolio;

    /** @var array $tutorcache Cache of tutor names per group id. */
    protected $tutorcache = [];

    /**
     * Render the students table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $CFG, $OUTPUT;

        $headers = [];
        $columns = [];

        // Select checkbox column.
        $mastercheckbox = new \core\output\checkbox_toggleall('students-table', true, [
            'id' => 'select-all-students',
            'name' => 'select-all-students',
            'label' => get_string('selectall'),
            'labelclasses' => 'visually-hidden',
            'classes' => 'm-1',
            'checked' => false,
        ]);
        $headers[] = $OUTPUT->render($mastercheckbox);
        $columns[] = 'select';

        $headers[] = get_string('fullname');
        $columns[] = 'fullname';

        $headers[] = get_string('email');
        $columns[] = 'email';

        // Get the list of fields we have to hide.
        $hiddenfields = [];
        if (!has_capability('moodle/course:viewhiddenuserfields', $this->context)) {
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
        }

        // Add column for groups if the user can view them.
        $canseegroups = !isset($hiddenfields['groups']);
        if ($canseegroups) {
            $headers[] = get_string('groups');
            $columns[] = 'groups';
        }

        // Tutor column (only when numberhours is enabled).
        $showtutor = $canseegroups;
        if ($showtutor) {
            $headers[] = get_string('tutor', 'mod_giportfolio');
            $columns[] = 'tutorname';
        }

        // Contribution status column.
        $headers[] = get_string('contributions', 'mod_giportfolio');
        $columns[] = 'contribcount';

        // Last access column.
        if (!isset($hiddenfields['lastaccess'])) {
            $headers[] = get_string('lastcourseaccess');
            $columns[] = 'lastaccess';
        }

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->define_header_column('fullname');
        $this->sortable(true, 'lastname');

        $this->no_sorting('select');
        if ($canseegroups) {
            $this->no_sorting('groups');
        }

        $this->set_default_per_page(20);
        $this->set_attribute('id', 'giportfolio-students');

        if ($canseegroups) {
            $this->groups = groups_get_all_groups($this->courseid, 0, 0, 'g.*', true);
        }

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * Generate the select column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('students-table', false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }

    /**
     * Generate the fullname column. Links to the user's portfolio, not their profile.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_fullname($data) {
        global $OUTPUT;

        $portfoliourl = new moodle_url('/mod/giportfolio/viewcontribute.php', [
            'id' => $this->cmid,
            'userid' => $data->id,
        ]);

        $userpic = $OUTPUT->user_picture($data, ['courseid' => $this->course->id, 'size' => 35]);
        $fullname = fullname($data);

        return $userpic . ' ' . \html_writer::link($portfoliourl, $fullname);
    }

    /**
     * Generate the email column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_email($data) {
        if ($this->is_downloading()) {
            return $data->email;
        }
        return \html_writer::link('mailto:' . $data->email, $data->email);
    }

    /**
     * Generate the groups column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_groups($data) {
        $usergroups = [];
        foreach ($this->groups as $coursegroup) {
            if (isset($coursegroup->members[$data->id])) {
                $usergroups[] = format_string($coursegroup->name, true, ['context' => $this->context]);
            }
        }
        return implode(', ', $usergroups);
    }

    /**
     * Generate the tutor column.
     *
     * Finds the non-student member(s) in the student's group(s).
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_tutorname($data) {
        $tutors = [];
        foreach ($this->groups as $coursegroup) {
            if (!isset($coursegroup->members[$data->id])) {
                continue;
            }
            // Check cache for this group's tutor.
            if (!isset($this->tutorcache[$coursegroup->id])) {
                $this->tutorcache[$coursegroup->id] = $this->get_group_tutors($coursegroup);
            }
            foreach ($this->tutorcache[$coursegroup->id] as $tutorname) {
                $tutors[$tutorname] = $tutorname;
            }
        }
        return implode(', ', $tutors);
    }

    /**
     * Get the non-student members (tutors) of a group.
     *
     * @param \stdClass $group The group object with members.
     * @return string[] Array of tutor full names.
     */
    protected function get_group_tutors(\stdClass $group): array {
        global $DB;

        $coursecontext = \context_course::instance($this->courseid);
        $studentroleids = array_keys(get_archetype_roles('student'));

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
        return $tutornames;
    }

    /**
     * Generate the last access column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_lastaccess($data) {
        if ($data->lastaccess) {
            return format_time(time() - $data->lastaccess);
        }
        return get_string('never');
    }

    /**
     * Generate the contribution count column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_contribcount($data) {
        $count = (int)$data->contribcount;
        if ($count > 0) {
            return \html_writer::span(
                get_string('hascontributions', 'mod_giportfolio'),
                'badge badge-success bg-success'
            );
        }
        return \html_writer::span(
            get_string('nocontributions', 'mod_giportfolio'),
            'badge badge-warning bg-warning'
        );
    }

    /**
     * Query the database for results to display in the table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        list($twhere, $tparams) = $this->get_sql_where();
        $psearch = new students_search($this->course, $this->context, $this->filterset,
            $this->cmid, $this->giportfolioid);

        $sort = $this->get_sql_sort();

        $this->use_pages = true;
        $rawdata = $psearch->get_participants($twhere, $tparams, $sort, $this->get_page_start(), $this->get_page_size());
        $total = $rawdata->current()->fullcount ?? 0;
        $this->pagesize($pagesize, $total);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            $this->rawdata[$user->id] = $user;
        }
        $rawdata->close();

        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Set the filterset, extracting course/context/cm info.
     *
     * @param filterset $filterset The filterset object to get the filters from.
     */
    public function set_filterset(filterset $filterset): void {
        global $DB;
        $this->courseid = $filterset->get_filter('courseid')->current();
        $this->course = get_course($this->courseid);

        // Extract cmid and giportfolioid from required filters (needed for AJAX webservice calls).
        $this->cmid = $filterset->get_filter('cmid')->current();
        $this->giportfolioid = $filterset->get_filter('giportfolioid')->current();
        $this->giportfolio = $DB->get_record('giportfolio', ['id' => $this->giportfolioid], '*', MUST_EXIST);

        // Use module context for capability checks.
        $this->context = \context_module::instance($this->cmid, MUST_EXIST);

        parent::set_filterset($filterset);
    }

    /**
     * Set the course module and giportfolio instance info.
     *
     * @param int $cmid The course module id.
     * @param int $giportfolioid The giportfolio instance id.
     */
    public function set_instance_info(int $cmid, int $giportfolioid): void {
        global $DB;
        $this->cmid = $cmid;
        $this->giportfolioid = $giportfolioid;
        $this->giportfolio = $DB->get_record('giportfolio', ['id' => $giportfolioid], '*', MUST_EXIST);
    }

    /**
     * Guess the base url for the table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/mod/giportfolio/submissions.php', [
            'id' => $this->cmid ?? 0,
            'tab' => 'reports',
        ]);
    }

    /**
     * Get the context of the current table.
     *
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Check if the user has the capability to access this table.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('mod/giportfolio:viewgiportfolios', $this->context);
    }
}
