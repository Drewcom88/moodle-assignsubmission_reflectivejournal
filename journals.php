<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Overview of a tutor's students' Journals: who has Entries waiting for a Comment.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_reflectivejournal\local\overview;

require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/assign:grade', $context);
$assign = new assign($context, $cm, $course);

$component = 'assignsubmission_reflectivejournal';
$url = new moodle_url('/mod/assign/submission/reflectivejournal/journals.php', ['id' => $cmid]);
$PAGE->set_url($url);
$assignname = format_string($assign->get_instance()->name, true, ['context' => $context]);
$PAGE->set_title(get_string('overviewheading', $component, $assignname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overviewheading', $component, $assignname));

groups_print_activity_menu($cm, $url);
$groupid = (int) groups_get_activity_group($cm, true);

$rows = overview::rows($assign, (int) $USER->id, $groupid);
if (!$rows) {
    echo $OUTPUT->notification(get_string('nostudents', $component), 'info');
} else {
    $table = new html_table();
    $table->id = 'reflectivejournal-overview';
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('student', $component),
        get_string('colentries', $component),
        get_string('colawaiting', $component),
        get_string('colstatus', $component),
        '',
    ];
    $max = $assign->get_submission_plugin_by_type('reflectivejournal')->get_journal_settings()->maxentries;
    foreach ($rows as $row) {
        $journalurl = new moodle_url(
            '/mod/assign/submission/reflectivejournal/journal.php',
            ['id' => $cmid, 'userid' => $row->studentid]
        );
        $table->data[] = [
            s($row->name),
            get_string('entriescount', $component, (object) ['count' => $row->entries, 'max' => $max]),
            $row->awaiting ? html_writer::span($row->awaiting, 'badge bg-warning text-dark') : 0,
            get_string($row->submitted ? 'statussubmitted' : 'statusinprogress', $component),
            html_writer::link($journalurl, get_string('viewjournal', $component)),
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::link(
    new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
    get_string('backtoassignment', $component),
    ['class' => 'btn btn-secondary']
);
echo $OUTPUT->footer();
