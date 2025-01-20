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
 * Class containing data for the view giportfolio page.
 *
 * @package    giportfoliotool_print
 * @copyright  2019 Mihail Geshoski
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace giportfoliotool_print\output;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use context_module;

/**
 * Class containing data for the print giportfolio page.
 *
 * @copyright  2019 Mihail Geshoski
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class print_giportfolio_page implements renderable, templatable {

    /**
     * @var object $giportfolio The giportfolio object.
     */
    protected $giportfolio;

    /**
     * @var object $cm The course module object.
     */
    protected $cm;

    /**
     * Construct this renderable.
     *
     * @param object $giportfolio The giportfolio
     * @param object $cm The course module
     */
    public function __construct($giportfolio, $cm, $userid) {
        $this->giportfolio = $giportfolio;
        $this->cm = $cm;
        $this->userid = $userid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT, $CFG, $SITE, $USER, $DB;

        $context = context_module::instance($this->cm->id);
        $chapters = giportfolio_preload_chapters($this->giportfolio);
        $course = get_course($this->giportfolio->course);

        $data = new stdClass();
        // Print dialog link.
        $data->printdialoglink = $output->render_print_giportfolio_dialog_link();
        $data->giportfoliotitle = $OUTPUT->heading(format_string($this->giportfolio->name, true,
                array('context' => $context)), 1);
        $introtext = file_rewrite_pluginfile_urls($this->giportfolio->intro, 'pluginfile.php', $context->id, 'mod_giportfolio', 'intro', null);
        $data->giportfoliointro = format_text($introtext, $this->giportfolio->introformat,
                array('noclean' => true, 'context' => $context));
        $data->sitelink = \html_writer::link(new moodle_url($CFG->wwwroot),
                format_string($SITE->fullname, true, array('context' => $context)));
        $data->coursename = format_string($course->fullname, true, array('context' => $context));
        $data->modulename = format_string($this->giportfolio->name, true, array('context' => $context));
        $data->username = fullname($USER, true);
        $data->printdate = userdate(time());

        if($this->giportfolio->numberhours) {

            $data->hashours = true;
            $data->totalhours =  get_total_hours($this->giportfolio->id, $USER->id);
        }


        $data->toc = $output->render_print_giportfolio_toc($chapters, $this->giportfolio, $this->cm);

        foreach ($chapters as $ch) {
            list($chaptercontent, $chaptervisible) = $output->render_print_giportfolio_chapter($ch, $chapters, $this->giportfolio,
                    $this->cm, $this->userid);
            $chapter = new stdClass();
            $chapter->content = $chaptercontent;
            $chapter->visible = $chaptervisible;
            $data->chapters[] = $chapter;
        }

        return $data;
    }
}
