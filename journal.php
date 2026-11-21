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
 * A student's Journal page, where their Tutor reads Locked Entries and writes Comments.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_reflectivejournal\form\comment_form;
use assignsubmission_reflectivejournal\local\journal;
use assignsubmission_reflectivejournal\local\presenter;
use assignsubmission_reflectivejournal\local\tutors;

require(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
$assign = new assign($context, $cm, $course);

if (!tutors::can_view($assign, $userid, (int) $USER->id)) {
    throw new required_capability_exception($context, 'mod/assign:grade', 'nopermissions', '');
}
$student = core_user::get_user($userid, '*', MUST_EXIST);
$url = new moodle_url('/mod/assign/submission/reflectivejournal/journal.php', ['id' => $cmid, 'userid' => $userid]);
$PAGE->set_url($url);
$PAGE->set_title(format_string($assign->get_instance()->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->activityheader->disable();

$journal = journal::for_user($assign, $userid);

// Handle a posted Comment before anything is printed.
$forms = [];
if ($journal) {
    foreach ($journal->entries_visible_to((int) $USER->id) as $entry) {
        if (!$journal->can_comment($entry->id, (int) $USER->id)) {
            continue;
        }
        $form = new comment_form($url, [
            'cmid' => $cmid, 'userid' => $userid, 'entryid' => $entry->id, 'context' => $context,
        ], 'post', '', ['id' => 'rj-comment-' . $entry->id]);
        if ($entry->comment) {
            $form->set_data(['comment_editor' => ['text' => $entry->comment->text, 'format' => $entry->comment->format]]);
        }
        $data = optional_param('entryid', 0, PARAM_INT) === $entry->id ? $form->get_data() : null;
        if ($data) {
            $journal->save_comment($entry->id, (int) $USER->id, $data->comment_editor['text'],
                (int) $data->comment_editor['format']);
            redirect($url, get_string('commentsaved', 'assignsubmission_reflectivejournal'), null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
        $forms[$entry->id] = $form;
    }
}

$name = $assign->is_blind_marking()
    ? get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($userid)
    : fullname($student);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('journalfor', 'assignsubmission_reflectivejournal', $name));
if (!$journal) {
    echo $OUTPUT->notification(get_string('noentries', 'assignsubmission_reflectivejournal'), 'info');
} else {
    echo presenter::journal($journal, (int) $USER->id, false, function($entry) use ($forms, $journal) {
        if (isset($forms[$entry->id])) {
            return $forms[$entry->id]->render();
        }
        $authorid = $journal->comment_in_progress_by($entry->id);
        if ($authorid && $authorid !== (int) $GLOBALS['USER']->id) {
            return html_writer::div(get_string('commentinprogress', 'assignsubmission_reflectivejournal',
                fullname(core_user::get_user($authorid))), 'text-muted small mt-2');
        }
        return '';
    });
}
echo html_writer::link(new moodle_url('/mod/assign/view.php', ['id' => $cmid, 'action' => 'grading']),
    get_string('backtojournal', 'assignsubmission_reflectivejournal'), ['class' => 'btn btn-secondary']);
echo $OUTPUT->footer();
