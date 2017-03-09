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
 * rpage module version information
 *
 * @package mod_rpage
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/rpage/lib.php');
require_once($CFG->dirroot.'/mod/rpage/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id      = optional_param('id', 0, PARAM_INT); // Course Module ID
$p       = optional_param('p', 0, PARAM_INT);  // rpage instance ID
$inpopup = optional_param('inpopup', 0, PARAM_BOOL);

if ($p) {
    if (!$rpage = $DB->get_record('rpage', array('id'=>$p))) {
        print_error('invalidaccessparameter');
    }
    $cm = get_coursemodule_from_instance('rpage', $rpage->id, $rpage->course, false, MUST_EXIST);

} else {
    if (!$cm = get_coursemodule_from_id('rpage', $id)) {
        print_error('invalidcoursemodule');
    }
    $rpage = $DB->get_record('rpage', array('id'=>$cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/rpage:view', $context);

// Completion and trigger events.
rpage_view($rpage, $course, $cm, $context);

$PAGE->set_url('/mod/rpage/view.php', array('id' => $cm->id));

$options = empty($rpage->displayoptions) ? array() : unserialize($rpage->displayoptions);

if ($inpopup and $rpage->display == RESOURCELIB_DISPLAY_POPUP) {
    $PAGE->set_rpagelayout('popup');
    $PAGE->set_title($course->shortname.': '.$rpage->name);
    $PAGE->set_heading($course->fullname);
} else {
    $PAGE->set_title($course->shortname.': '.$rpage->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($rpage);
}
echo $OUTPUT->header();

if (!isset($options['printheading']) || !empty($options['printheading'])) {
    echo $OUTPUT->heading(format_string($rpage->name), 2);
}

if (!empty($options['printintro'])) {
    if (trim(strip_tags($rpage->intro))) {
        echo $OUTPUT->box_start('mod_introbox', 'rpageintro');
        echo format_module_intro('rpage', $rpage, $cm->id);
        echo $OUTPUT->box_end();
    }
}

$content = file_rewrite_pluginfile_urls($rpage->content, 'pluginfile.php', $context->id, 'mod_rpage', 'content', $rpage->revision);
$formatoptions = new stdClass;
$formatoptions->noclean = true;
$formatoptions->overflowdiv = true;
$formatoptions->context = $context;
$content = format_text($content, $rpage->contentformat, $formatoptions);
echo $OUTPUT->box($content, "generalbox center clearfix");

// Print list here
echo '<h4>You are currently booked for the following session</h4>';
global $USER;

$result = $DB->get_records_sql("SELECT rr.id, r.name, r.location, r.timestart, r.timeend, rr.timecancelled
                                        FROM {reservation_request} as rr
                                        INNER JOIN {reservation} as r
                                        ON rr.userid = ? AND rr.reservation = r.id;",
    array($USER->id));
echo "<style>
            td  {
                border:1px solid black
                } 
            th {
                border:1px solid black; 
                background-color: #AAAAAA;
                }
     </style>";
echo '<table style="width:100%; border:1px solid black">';
echo "<tr>";
echo "<th>Session</th><th>Venue</th><th>Start Time</th><th>End Time</th>";
echo "</tr>";
foreach($result as $r => $r_val) {
    echo "<tr>";
    if($r_val->timecancelled==0) {
        echo "<td>" . $r_val->name . "</td><td>" . $r_val->location . "</td><td>" . userdate($r_val->timestart) . "</td><td>" . userdate($r_val->timeend) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";
//$strlastmodified = get_string("lastmodified");
//echo "<div class=\"modified\">$strlastmodified: ".userdate($rpage->timemodified)."</div>";

echo $OUTPUT->footer();
