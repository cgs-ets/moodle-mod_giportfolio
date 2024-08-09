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
class print_giportfolio_chapter_page implements renderable, templatable {

    /**
     * @var object $giportfolio The giportfolio object.
     */
    protected $giportfolio;

    /**
     * @var object $cm The course module object.
     */
    protected $cm;

    /**
     * @var object $chapter The giportfolio chapter object.
     */
    protected $chapter;

    /**
     * @var object $contribution The giportfolio contribution object.
     */
    protected $contribution;

    /**
     * @var object $userid The giportfolio userid object.
     */
    protected $userid;


    /**
     * Construct this renderable.
     *
     * @param object $giportfolio The giportfolio
     * @param object $cm The course module
     * @param object $chapter The giportfolio chapter
     * @param string $userid  The id of the owner of the chapter
     */
    public function __construct($giportfolio, $cm, $chapter, $userid) {
        $this->giportfolio = $giportfolio;
        $this->cm = $cm;
        $this->chapter = $chapter;
        $this->userid = $userid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $OUTPUT;

        $context = context_module::instance($this->cm->id);
        $chapters = giportfolio_preload_chapters($this->giportfolio);

        $data = new stdClass();
        // Print dialog link.
        $data->printdialoglink = $output->render_print_giportfolio_chapter_dialog_link();
        $data->giportfoliotitle = $OUTPUT->heading(format_string($this->giportfolio->name, true,
                array('context' => $context)), 1);
        if (!$this->giportfolio->customtitles) {
            // If the current chapter is a subchapter, get the title of the parent chapter.
            if ($this->chapter->subchapter) {
                $parentchaptertitle = giportfolio_get_chapter_title($chapters[$this->chapter->id]->parent, $chapters,
                        $this->giportfolio, $context);
                $data->parentchaptertitle = $OUTPUT->heading(format_string($parentchaptertitle, true,
                        array('context' => $context)), 2);
            }
        }

        list($chaptercontent, $chaptervisible) = $output->render_print_giportfolio_chapter($this->chapter, $chapters,
                $this->giportfolio, $this->cm, $this->userid);
        $chapter = new stdClass();
        $chapter->content = $chaptercontent;
        $chapter->visible = $chaptervisible;
        $data->chapter = $chapter;

        return $data;
    }
}
