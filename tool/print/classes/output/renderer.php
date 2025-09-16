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
 * Defines the renderer for the giportfolio print tool.
 *
 * @package    giportfoliotool_print
 * @copyright  2019 Mihail Geshoski
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace giportfoliotool_print\output;

defined('MOODLE_INTERNAL') || die();

use comment;
use plugin_renderer_base;
use html_writer;
use context_module;
use moodle_url;
use moodle_exception;

/**
 * The renderer for the giportfolio print tool.
 *
 * @copyright  2019 Mihail Geshoski
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the print giportfolio page.
     *
     * @param print_giportfolio_page $page
     * @return string html for the page
     * @throws moodle_exception
     */
    public function render_print_giportfolio_page(print_giportfolio_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('giportfoliotool_print/print_giportfolio', $data);
    }

    /**
     * Render the print giportfolio chapter page.
     *
     * @param print_giportfolio_chapter_page $page
     * @return string html for the page
     * @throws moodle_exception
     */
    public function render_print_giportfolio_chapter_page(print_giportfolio_chapter_page $page) {
        $data = $page->export_for_template($this);
        return parent::render_from_template('giportfoliotool_print/print_giportfolio_chapter', $data);
    }

    /**
     * Render the print giportfolio chapter link.
     *
     * @return string html for the link
     */
    public function render_print_giportfolio_chapter_dialog_link() {
        $printtext = get_string('printchapter', 'giportfoliotool_print');
        $printicon = $this->output->pix_icon(
            'print',
            $printtext,
            'giportfoliotool_print',
            array('class' => 'icon')
        );
        $printlinkatt = array('onclick' => 'window.print();return false;', 'class' => 'hidden-print');
        return html_writer::link('#', $printicon . $printtext, $printlinkatt);
    }

    /**
     * Render the print giportfolio link.
     *
     * @return string html for the link
     */
    public function render_print_giportfolio_dialog_link() {
        $printtext = get_string('printgiportfolio', 'giportfoliotool_print');
        $printicon = $this->output->pix_icon(
            'print',
            $printtext,
            'giportfoliotool_print',
            array('class' => 'icon')
        );
        $printlinkatt = array('onclick' => 'window.print();return false;', 'class' => 'hidden-print');
        return html_writer::link('#', $printicon . $printtext, $printlinkatt);
    }

    /**
     * Render the print giportfolio table of contents.
     *
     * @param array $chapters Array of giportfolio chapters
     * @param object $giportfolio The giportfolio object
     * @param object $cm The curse module object
     * @return string html for the TOC
     */
    public function render_print_giportfolio_toc($chapters, $giportfolio, $cm) {

        $first = true;

        $context = context_module::instance($cm->id);

        $toc = ''; // Representation of toc (HTML).

        switch ($giportfolio->numbering) {
            case PORTFOLIO_NUM_NONE:
                $toc .= html_writer::start_tag('div', array('class' => 'giportfolio_toc_none'));
                break;
            case PORTFOLIO_NUM_NUMBERS:
                $toc .= html_writer::start_tag('div', array('class' => 'giportfolio_toc_numbered'));
                break;
            case PORTFOLIO_NUM_BULLETS:
                $toc .= html_writer::start_tag('div', array('class' => 'giportfolio_toc_bullets'));
                break;
            case PORTFOLIO_NUM_INDENTED:
                $toc .= html_writer::start_tag('div', array('class' => 'giportfolio_toc_indented'));
                break;
        }

        $toc .= html_writer::tag('a', '', array('name' => 'toc')); // Representation of toc (HTML).

        $toc .= html_writer::tag('h2', get_string('toc', 'mod_giportfolio'), ['class' => 'text-center p-b-2']);
        $toc .= html_writer::start_tag('ul');
        foreach ($chapters as $ch) {
            if (!$ch->hidden) {
                $title = giportfolio_get_chapter_title($ch->id, $chapters, $giportfolio, $context);
                if (!$ch->subchapter) {

                    if ($first) {
                        $toc .= html_writer::start_tag('li');
                    } else {
                        $toc .= html_writer::end_tag('ul');
                        $toc .= html_writer::end_tag('li');
                        $toc .= html_writer::start_tag('li');
                    }
                } else {

                    if ($first) {
                        $toc .= html_writer::start_tag('li');
                        $toc .= html_writer::start_tag('ul');
                        $toc .= html_writer::start_tag('li');
                    } else {
                        $toc .= html_writer::start_tag('li');
                    }
                }

                if (!$ch->subchapter) {
                    $toc .= html_writer::link(
                        new moodle_url('#ch' . $ch->id),
                        $title,
                        array('title' => s($title), 'class' => 'font-weight-bold text-decoration-none')
                    );
                    $toc .= html_writer::start_tag('ul');
                } else {
                    $toc .= html_writer::link(
                        new moodle_url('#ch' . $ch->id),
                        $title,
                        array('title' => s($title), 'class' => 'text-decoration-none')
                    );
                    $toc .= html_writer::end_tag('li');
                }
                $first = false;
            }
        }

        $toc .= html_writer::end_tag('ul');
        $toc .= html_writer::end_tag('li');
        $toc .= html_writer::end_tag('ul');
        $toc .= html_writer::end_tag('div');

        $toc = str_replace('<ul></ul>', '', $toc); // Cleanup of invalid structures.

        return $toc;
    }

    /**
     * Render the print giportfolio chapter.
     *
     * @param object $chapter The giportfolio chapter object
     * @param array $chapters The array of giportfolio chapters
     * @param object $giportfolio The giportfolio object
     * @param object $cm The course module object
     * @return array The array containing the content of the giportfolio chapter and visibility information
     */
    public function render_print_giportfolio_chapter($chapter, $chapters, $giportfolio, $cm, $userid) {
        global $COURSE, $DB;

        $context = context_module::instance($cm->id);
        $title = giportfolio_get_chapter_title($chapter->id, $chapters, $giportfolio, $context);
        $contributions = giportfolio_get_user_contributions($chapter->id, $giportfolio->id, $userid);
        $chaptervisible = $chapter->hidden ? false : true;

        $giportfoliochapter = '';
        $giportfoliochapter .= html_writer::start_div('giportfolio_chapter p-t-1', ['id' => 'ch' . $chapter->id]);
        if (!$giportfolio->customtitles) {
            if (!$chapter->subchapter) {
                $giportfoliochapter .= $this->output->heading($title, 2, 'text-center p-b-2');
            } else {
                $giportfoliochapter .= $this->output->heading($title, 3, 'text-center p-b-2');
            }
        }

        // Check if content property exists, if not set empty string
        $content = isset($chapter->content) ? $chapter->content : '';
        $contentformat = isset($chapter->contentformat) ? $chapter->contentformat : FORMAT_HTML;
        
        $chaptertext = file_rewrite_pluginfile_urls(
            $content,
            'pluginfile.php',
            $context->id,
            'mod_giportfolio',
            'chapter',
            $chapter->id
        );

        $giportfoliochapter .= '<br><br>';
        $giportfoliochapter .= format_text($chaptertext, $contentformat, array('noclean' => true, 'context' => $context));
        // Add the contributions.
        $giportfoliochaptercontributions = '';
        $giportfoliochaptercontributions .= html_writer::start_div('giportfolio_chapter_contribution p-t-1', ['id' => 'ch' . $chapter->id]);

        if ($contributions) {

            if($giportfolio->numberhours) {
                $newtitle = $title . ' - ' . 'Contributions';
                $giportfoliochaptercontributions .= $this->output->heading($newtitle, 2, 'text-center p-b-2') . '<br><br>';
                $totalhours = get_total_hours_by_chapter_contribution($chapter->id, $userid);

                $giportfoliochaptercontributions .= $this->output->heading(get_string('totalhoursregistered', 'giportfoliotool_print', $totalhours), 3, 'text-right p-b-2') . '<br><br>';

            } else {
                $giportfoliochaptercontributions .= $this->output->heading('Contributions', 2, 'text-center p-b-2');
            }

            $counter = 0;
            foreach ($contributions as $contribution) {

                if ($counter > 0) {
                    $giportfoliochaptercontributions .= '<hr class="hr-giportfolioprint">';
                }
                $contheading = '';
                // Get author of the contribution
                if ($contribution->mentorid != 0) {
                    $uid = $contribution->mentorid;
                    $author = get_contribution_author($uid);
                    $contheading = '<span class="badge badge-info contributor-tag" >' . format_string(get_string('mentorcontribution', 'giportfoliotool_print', $author)) . '</span><br>';
                } else if ($contribution->teacherid != 0) {
                    $uid = $contribution->teacherid;
                    $author = get_contribution_author($uid);
                    $contheading =  '<span class="badge badge-success contributor-tag" >' . format_string(get_string('teachercontribution', 'giportfoliotool_print', $author)) . '</span><br>';
                } else {
                    $uid = $contribution->userid;
                    $author = get_contribution_author($uid);
                }

                $giportfoliochaptercontributions .=  "<strong> $contribution->title </strong> $contheading";

                if ($contribution->timecreated !== $contribution->timemodified) {
                    $giportfoliochaptercontributions .= '<i> ' . get_string('lastmodified', 'mod_giportfolio') . date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contribution->timemodified) . '</i>';
                } else {
                    $giportfoliochaptercontributions .= '<i> ' . get_string('lastupdated', 'mod_giportfolio') . date('l jS F Y' . ($giportfolio->timeofday ? ' h:i A' : ''), $contribution->timecreated) . '</i>';
                }

                $giportfoliochaptercontributions .= "<br><br>";

                if ($giportfolio->numberhours && $contribution->numhours != null) {
                    $contribution->numhours = number_format($contribution->numhours, 2);
                    $giportfoliochaptercontributions .= "<strong>" . get_string('hours', 'giportfoliotool_print') . ": </strong>" . $contribution->numhours; //hours
                    // $totalhours +=  $contribution->numhours;
                }

                $giportfoliochaptercontributions .= "<br><br>";

                $contributiontext = file_rewrite_pluginfile_urls(
                    $contribution->content,
                    'pluginfile.php',
                    $context->id,
                    'mod_giportfolio',
                    'contribution',
                    $contribution->id
                );


                $giportfoliochaptercontributions .= format_text($contributiontext, $contribution->contentformat, array('noclean' => true, 'context' => $context));
                $files = giportfolio_print_attachments($contribution, $cm, 'html', $align = "right");

                if ($files) {
                    $table = "<table border=\"0\" width=\"100%\" align=\"$align\"><tr><td align=\"$align\">\n $files </td></tr></table>\n";
                    $giportfoliochaptercontributions .=  $table;
                }

                // Get comments.

                $comments = giportfolio_print_comments($contribution);

                if ($comments) {
                    comment::init();
                    $commentopts = (object) array(
                        'context' => $context,
                        'component' => 'mod_giportfolio',
                        'area' => 'giportfolio_contribution',
                        'cm' => $cm,
                        'course' => $COURSE,
                        'autostart' => true,
                    );

                    $commentopts->itemid = $contribution->id;
                    $commentbox = new \comment($commentopts);
                    $giportfoliochaptercontributions .= html_writer::tag('contribcomment', $commentbox->output(true));
                    $giportfoliochaptercontributions .= '<br>';
                }
                $counter++;
            }

            $giportfoliochaptercontributions .= html_writer::end_div();
            $giportfoliochapter .= $giportfoliochaptercontributions;

        }

        $giportfoliochapter .= html_writer::end_div();

        return array($giportfoliochapter, $chaptervisible);
    }
}
