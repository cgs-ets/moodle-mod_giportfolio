<?php

// This file is part of giportfolio module for Moodle - http://moodle.org/
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

require_once("../../config.php");
require_once("lib.php");
require_once(dirname(__FILE__) . '/locallib.php');

global $CFG, $DB, $USER, $DB, $OUTPUT, $PAGE;

require_once($CFG->libdir . '/plagiarismlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once("search_form.php");

$id = optional_param('id', 0, PARAM_INT); // Course module ID.
$p = optional_param('p', 0, PARAM_INT); // Giportfolio ID.
$currenttab = optional_param('tab', 'all', PARAM_ALPHA); // What tab are we in?
$username = optional_param('username', '', PARAM_ALPHA); // Giportfolio ID.
$url = new moodle_url('/mod/giportfolio/submissions.php');

if ($id) {
    $cm = get_coursemodule_from_id('giportfolio', $id, 0, false, MUST_EXIST);
    $giportfolio = $DB->get_record("giportfolio", array("id" => $cm->instance), '*', MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $giportfolio->course), '*', MUST_EXIST);
    $url->param('id', $id);
} else {
    $giportfolio = $DB->get_record("giportfolio", array("id" => $p), '*', MUST_EXIST);
    $course = $DB->get_record("course", array("id" => $giportfolio->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance("giportfolio", $giportfolio->id, $course->id, false, MUST_EXIST);
    $url->param('p', $p);
}

if ($currenttab !== 'all') {
    $url->param('tab', $currenttab);
}
$PAGE->set_url($url);
require_login($course->id, false, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/giportfolio:gradegiportfolios', $context);
require_capability('mod/giportfolio:viewgiportfolios', $context);

$PAGE->set_title(format_string($giportfolio->name));
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($giportfolio->name));

$alias = get_student_alias($COURSE); // Pick the alias given to the students

// Set up the list of tabs.
$allurl = new moodle_url($PAGE->url);
$allurl->remove_params('tab');
$sincelastloginurl = new moodle_url($PAGE->url, array('tab' => 'sincelastlogin'));
$nocommentsurl = new moodle_url($PAGE->url, array('tab' => 'nocomments'));
$graphcontributorsurl = new moodle_url($PAGE->url, array('tab' => 'graphcontributors'));
$tabs = array(
    new tabobject('all', $allurl, get_string('allusers', 'mod_giportfolio', $alias)),
    new tabobject('sincelastlogin', $sincelastloginurl, get_string('sincelastlogin', 'mod_giportfolio')),
    new tabobject('nocomments', $nocommentsurl, get_string('nocomments', 'mod_giportfolio')),
    new tabobject('graphcontributors', $graphcontributorsurl, get_string('graphofcontributors', 'mod_giportfolio')),
);

echo get_string('studentgiportfolios', 'mod_giportfolio', $alias);
echo '</br>';
echo $OUTPUT->tabtree($tabs, $currenttab);
echo get_string('filterlist', 'mod_giportfolio');


// Check to see if groups are being used in this assignment.
// Find out current groups mode.
$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);

groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/giportfolio/submissions.php?id=' . $cm->id . '&tab=' . $currenttab);

$updatepref = optional_param('updatepref', 0, PARAM_BOOL);

if ($updatepref) {
    $perpage = optional_param('perpage', 10, PARAM_INT);
    $perpage = ($perpage <= 0) ? 10 : $perpage;
    $filter = optional_param('filter', 0, PARAM_INT);
    set_user_preference('giportfolio_perpage', $perpage);
    set_user_preference('giportfolio_quickgrade', optional_param('quickgrade', 0, PARAM_BOOL));
    set_user_preference('giportfolio_filter', $filter);
}

$perpage = get_user_preferences('giportfolio_perpage', 10);
$quickgrade = get_user_preferences('giportfolio_quickgrade', 0);
$filter = get_user_preferences('giportfoliot_filter', 0);

$page = optional_param('page', 0, PARAM_INT);
$strsaveallfeedback = get_string('saveallfeedback', 'mod_giportfolio');
$fastg = optional_param('fastg', 0, PARAM_BOOL);

if ($fastg) { // Update the grade and the feedback.
   
    if (isset($_POST["menu"])) {
        $menu = $_POST["menu"];
        giportfolio_quick_update_grades($cm->id, $menu, $currentgroup, $giportfolio->id);
    }
    if (isset($_POST["submissionfeedback"])) {
        $submissionfeedback = $_POST["submissionfeedback"];
        giportfolio_quick_update_feedback($cm->id, $submissionfeedback, $currentgroup, $giportfolio->id);
    }
    echo html_writer::start_tag('div', array('class' => 'notifysuccess'));
    echo get_string('changessaved');
    echo html_writer::end_tag('div');
}
$mform = new giportfolio_search_form(null, array('id' => $id, 'tab' => $currenttab));
$mform->display();

// Print quickgrade form around the table.
if ($quickgrade && $currenttab != 'graphcontributors') {

    $formattrs = array();
    $formattrs['action'] = new moodle_url('/mod/giportfolio/submissions.php');
    $formattrs['id'] = 'fastg';
    $formattrs['method'] = 'post';

    echo html_writer::start_tag('form', $formattrs);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cm->id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'mode', 'value' => 'fastgrade'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'page', 'value' => $page));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
}

$allusers = get_users_by_capability($context, 'mod/giportfolio:submitportfolio', 'u.id,u.picture,u.firstname,u.lastname,u.idnumber',
    'u.firstname ASC', '', '', $currentgroup, '', false, true);

$alluserids = array();
foreach ($allusers as $user) {
    array_push($alluserids, $user->id);
}

$listusersids = "'" . implode("', '", $alluserids) . "'";
// Generate table.
if ($currenttab == 'graphcontributors') {

    giportfolio_graph_of_contributors($PAGE, $allusers, $context, $username, $listusersids, $perpage, $page, $giportfolio, $course, $cm);

    $iconunseen = html_writer::span('<i class = "fa">&#xf096;</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('unseencontrib', 'mod_giportfolio')]);
    $iconseen = html_writer::span('<i class = "fa">&#xf046;</i>', '', ['class' => 'giportfolio-legend']);
    $iconnocontrib = html_writer::span('<i class = "fa">&#xf068;</i>', '', ['class' => 'giportfolio-legend']);
    $iconcomment =  html_writer::span('<i class = "fa">&#xf075;</i>', '', ['class' => 'giportfolio-legend']);
    $iconnocomment = html_writer::span('<i class = "fa">&#xf0e5;</i>', '', ['class' => 'giportfolio-legend']);
    $iconcomments = html_writer::span('<i class = "fa">&#xf086</i>', '', ['class' => 'giportfolio-legend', 'title' => get_string('contrcomments', 'mod_giportfolio')]);

    $out .= html_writer::start_div();
    $out .= "<table>
             <tr>
                <th>".get_string('legends', 'mod_giportfolio' )."</th>
                <th></th>  
            </tr>".
            "<tr>
                <td>".get_string('nocontrib', 'mod_giportfolio') ." </td>
                <td>".$iconnocontrib ." </td>
            </tr>".
            "<tr>
                <td>".get_string('unseencontrib', 'mod_giportfolio' )."</td>
                <td>".$iconunseen. "</td>
            </tr>".
            "<tr>
                <td>".get_string('multipleunseen', 'mod_giportfolio' )."</td>
                <td>". $iconunseen.' '.$iconunseen. "</td>
            </tr>".
            "<tr>
                <td>".get_string('seencontrib', 'mod_giportfolio' )."</td>
                <td>". $iconseen. "</td>
            </tr>".
            "<tr>
                <td>".get_string('multipleseen', 'mod_giportfolio' )."</td>
                <td>". $iconseen.' '.$iconseen. "</td>
            </tr>".
            "<tr>
                <td>".get_string('contrcomment', 'mod_giportfolio' )."</td>
                <td>". $iconcomment. "</td>
             </tr>".
            "<tr>
                <td>".get_string('contrnocomment', 'mod_giportfolio' )."</td>
                <td>". $iconnocomment. "</td>
            </tr>".
            "<tr>
                <td>".get_string('contrcomments', 'mod_giportfolio' )."</td>
                <td>". $iconcomments. "</td>
            </tr>".
            "<tr>
                <td>".get_string('additionstitle', 'mod_giportfolio' )."</td>
                <td>". get_string('additionlegend', 'mod_giportfolio', $alias ). "</td>               
            </tr>".    
    "</table>";
    $out .= html_writer::end_div();
   
    echo $out;

} else {
    giportfolio_submissionstables($context, $username, $currenttab, $giportfolio, $allusers,
        $listusersids, $perpage, $page, $cm, $url, $course, $quickgrade, $filter);
   
}


echo $OUTPUT->footer();

/**
 * Checks if grading method allows quickgrade mode. At the moment it is hardcoded
 * that advanced grading methods do not allow quickgrade.
 *
 * Assignment type plugins are not allowed to override this method
 *
 * @param $cmid
 * @return boolean
 */
function quickgrade_mode_allowed($cmid) {
    global $CFG;
    require_once("$CFG->dirroot/grade/grading/lib.php");
    $context = context_module::instance($cmid);

    if ($controller = get_grading_manager($context->id, 'mod_giportfolio', 'submission')->get_active_controller()) {
        return false;
    }
    return true;
}

// Part of CGS customisation.  List chapters with new contribution.
function get_updated_chapters_not_seen($giportfolio, $contributorid, $cm) {
    global $DB, $USER, $PAGE;
    $conditions = array ('giportfolioid' => $giportfolio->id, 'userid' => $contributorid);
    $countcontributions = $DB->count_records('giportfolio_contributions', $conditions);

    if ($countcontributions > 0 ) {

        // Get contribution ids done by the contributor.
        $select = "giportfolioid = $giportfolio->id AND userid = $contributorid";
        $contribids = $DB->get_fieldset_select('giportfolio_contributions', 'id', $select);
        $contributionids = implode(',', $contribids);

        // Get the ids of the contributions seen.
        $select = "contributionid  IN ($contributionids)
                   AND userid = $USER->id
                   AND giportfolioid = $giportfolio->id";
        $contribseen = $DB->get_fieldset_select('giportfolio_follow_updates', 'contributionid', $select);

        // Filter the contribution ids to only have the ids not seen.
        $contribnotseenids = implode(',', array_diff($contribids, $contribseen));

        if (empty ($contribnotseenids) || empty($contribids)) {
            return '';
        } else {

            $q = "SELECT DISTINCT chapterid FROM mdl_giportfolio_contributions WHERE id IN ($contribnotseenids);";
            $chids = array_keys($DB->get_records_sql($q));
            $chids = implode(',', $chids);
            $sql = "SELECT * FROM mdl_giportfolio_chapters WHERE id in ($chids)";
        }

        return $DB->get_records_sql($sql);
    }

}

function display_chapters_not_seen( $giportfolio, $contributorid, $cm) {
    global $DB, $PAGE;

    $chapters =  get_updated_chapters_not_seen($giportfolio, $contributorid, $cm);
    $morethanthree = count($chapters) > 3;
    $links = '';
    $index = 0;

    // In case the chapter has no content, by pass it
    $conditions = array ('giportfolioid' => $giportfolio->id, 'userid' => $contributorid);
    $countcontributions = $DB->count_records('giportfolio_contributions', $conditions);
 
    if ($countcontributions > 0 ) {
        foreach ($chapters as $chapter) {

            $url = new moodle_url('/mod/giportfolio/viewcontribute.php', array('id' => $cm->id, 'chapterid' => $chapter->id,
                'userid' => $contributorid, 'cont' => 'no'));
            if ($index >= 3) {
                $params = [
                    'href' => $url,
                    'target' => '_blank',
                    'class' => 'giportfolio-updatedch' . ' contributor_' . $contributorid,
                    'id' => 'contributor_' . $contributorid
                ];
                $links .= html_writer::tag("a", $chapter->title, $params);
            } else {
                $links .= html_writer::tag('a', $chapter->title, ['href' => $url, 'target' => '_blank']) . '<br>';
            }

            $index++;
        }

        if ($morethanthree) {
            $params = ["class" => "giportfolio-more", "id" => $contributorid, 'title' => 'Show More'];
            $icon = '<i class = "fa">&#xf067;</i>'; //minus: &#xf068;
            $links .= html_writer::span($icon, '', $params);
            $jsmodule = array(
                'name' => 'mod_giportfolio_morechapters',
                'fullpath' => new moodle_url('/mod/giportfolio/morechapters.js'),
                'contributorid' => $contributorid
            );
            $PAGE->requires->js_init_call('M.mod_giportfolio_showMore.init', array($contributorid), true, $jsmodule);
        }

        return $links;

    } else {
        return '';
    }

}

