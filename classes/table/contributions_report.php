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
 * Dynamic table for the contribution report (one column per chapter per year).
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

class contributions_report extends table_sql implements dynamic_table {

    /** @var int */
    protected $courseid;

    /** @var int */
    protected $cmid;

    /** @var int */
    protected $giportfolioid;

    /** @var int Selected year for chapter columns. */
    protected $year;

    /** @var \stdClass[] Template chapters for the selected year, keyed by id. */
    protected $chapters = [];

    /** @var bool[][] Contribution map: [userid][chapterid] = true */
    protected $contribmap = [];

    /** @var \stdClass */
    protected $course;

    /** @var context */
    protected $context;

    /** @var moodle_url */
    public $baseurl;

    /**
     * Render the table with dynamic chapter columns.
     *
     * @param int    $pagesize
     * @param bool   $useinitialsbar
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $OUTPUT;

        // Load template chapters for the selected year.
        $this->chapters = $this->get_chapters_for_year($this->year);

        $headers = [];
        $columns = [];

        // Select checkbox.
        $mastercheckbox = new \core\output\checkbox_toggleall('contributions-table', true, [
            'id'          => 'select-all-contributions',
            'name'        => 'select-all-contributions',
            'label'       => get_string('selectall'),
            'labelclasses' => 'visually-hidden',
            'classes'     => 'm-1',
            'checked'     => false,
        ]);
        $headers[] = $OUTPUT->render($mastercheckbox);
        $columns[]  = 'select';

        $headers[] = get_string('fullname');
        $columns[]  = 'fullname';

        $headers[] = get_string('email');
        $columns[]  = 'email';

        $headers[] = get_string('supervisor', 'mod_giportfolio');
        $columns[]  = 'supervisor';

        // One column per chapter.
        foreach ($this->chapters as $chapter) {
            $headers[] = format_string($chapter->title);
            $columns[]  = 'chapter_' . $chapter->id;
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_header_column('fullname');
        $this->sortable(true, 'lastname');
        $this->no_sorting('select');
        $this->no_sorting('supervisor');
        foreach ($this->chapters as $chapter) {
            $this->no_sorting('chapter_' . $chapter->id);
        }
        $this->column_class('supervisor', 'd-none');

        $this->set_default_per_page(20);
        $this->set_attribute('id', 'giportfolio-contributions-report');

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);
    }

    /**
     * Query the database for results.
     *
     * @param int  $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        // Build contribmap for all chapters in one batch query.
        $this->contribmap = [];
        if (!empty($this->chapters)) {
            $chapterids = array_keys($this->chapters);
            [$insql, $inparams] = $DB->get_in_or_equal($chapterids, SQL_PARAMS_NAMED, 'chap');
            $inparams['contribgid'] = $this->giportfolioid;
            $rows = $DB->get_records_sql(
                "SELECT id, userid, chapterid
                   FROM {giportfolio_contributions}
                  WHERE giportfolioid = :contribgid AND chapterid {$insql}",
                $inparams
            );
            foreach ($rows as $row) {
                $this->contribmap[$row->userid][$row->chapterid] = true;
            }
        }

        [$twhere, $tparams] = $this->get_sql_where();
        $psearch = new contributions_search($this->course, $this->context, $this->filterset,
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
     * Select checkbox column.
     */
    public function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('contributions-table', false, [
            'classes'      => 'usercheckbox m-1',
            'id'           => 'user' . $data->id,
            'name'         => 'user' . $data->id,
            'checked'      => false,
            'label'        => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }

    /**
     * Fullname column — links to the user's portfolio.
     */
    public function col_fullname($data) {
        global $OUTPUT;

        $portfoliourl = new moodle_url('/mod/giportfolio/viewcontribute.php', [
            'id'     => $this->cmid,
            'userid' => $data->id,
        ]);

        $userpic  = $OUTPUT->user_picture($data, ['courseid' => $this->course->id, 'size' => 35]);
        $fullname = fullname($data);

        return $userpic . ' ' . \html_writer::link($portfoliourl, $fullname);
    }

    /**
     * Email column.
     */
    public function col_email($data) {
        if ($this->is_downloading()) {
            return $data->email;
        }
        return \html_writer::link('mailto:' . $data->email, $data->email);
    }

    /**
     * Supervisor column.
     */
    public function col_supervisor($data) {
        return $data->supervisorname ?? '';
    }

    /**
     * Dynamic chapter columns handled here.
     */
    public function other_cols($column, $data) {
        if (str_starts_with($column, 'chapter_')) {
            $chapterid = (int) substr($column, 8);
            return isset($this->contribmap[$data->id][$chapterid])
                ? get_string('contributed', 'mod_giportfolio')
                : get_string('notcontributed', 'mod_giportfolio');
        }
        return null;
    }

    /**
     * Set the filterset, extracting course/context/cm/year info.
     *
     * @param filterset $filterset
     */
    public function set_filterset(filterset $filterset): void {
        $this->courseid      = $filterset->get_filter('courseid')->current();
        $this->course        = get_course($this->courseid);
        $this->cmid          = $filterset->get_filter('cmid')->current();
        $this->giportfolioid = $filterset->get_filter('giportfolioid')->current();
        $this->year          = (int) $filterset->get_filter('year')->current();
        $this->context       = \context_module::instance($this->cmid, MUST_EXIST);

        parent::set_filterset($filterset);
    }

    /**
     * Pre-set module/instance info (used before set_filterset on initial render).
     *
     * @param int $cmid
     * @param int $giportfolioid
     */
    public function set_instance_info(int $cmid, int $giportfolioid): void {
        $this->cmid          = $cmid;
        $this->giportfolioid = $giportfolioid;
    }

    /**
     * Return the filterset class for this dynamic table.
     *
     * @return string
     */
    public static function get_filterset_class(): string {
        return contributions_filterset::class;
    }

    /**
     * Guess the base URL.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/mod/giportfolio/submissions.php', [
            'id'        => $this->cmid ?? 0,
            'tab'       => 'reports',
            'subreport' => 'contributions',
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
     * Check capability.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('mod/giportfolio:viewgiportfolios', $this->context);
    }

    /**
     * Return template chapters for a given year, ordered by pagenum.
     * Uses Unix timestamp range comparisons — DB-agnostic.
     *
     * @param  int        $year Calendar year (e.g. 2026).
     * @return \stdClass[]       Associative array keyed by chapter id.
     */
    public function get_chapters_for_year(int $year): array {
        global $DB;

        $yearstart = mktime(0, 0, 0, 1, 1, $year);
        $yearend   = mktime(0, 0, 0, 1, 1, $year + 1);

        return $DB->get_records_select(
            'giportfolio_chapters',
            'giportfolioid = :gid AND userid = 0
             AND timecreated >= :yearstart AND timecreated < :yearend',
            ['gid' => $this->giportfolioid, 'yearstart' => $yearstart, 'yearend' => $yearend],
            'pagenum ASC',
            'id, title, pagenum'
        );
    }
}
