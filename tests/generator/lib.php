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
 * Test data generator for the Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_reflectivejournal\local\journal;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/tests/generator/assignsubmission_subplugin_generator.php');

/**
 * Test data generator for the Reflective journal submission plugin.
 */
class assignsubmission_reflectivejournal_generator extends assignsubmission_subplugin_generator {
    /**
     * Add Entry text to an assign "submissions" generator call (column "reflectivejournal").
     *
     * @param stdClass $submission The submission form data being built.
     * @param assign $assign The Assignment.
     * @param array $data Generator data.
     */
    public function add_submission_data(stdClass $submission, assign $assign, array $data): void {
        $submission->reflectivejournal_model = $data['model'] ?? 'none';
        $submission->reflectivejournal_editor = ['text' => $data['reflectivejournal'], 'format' => FORMAT_HTML];
    }

    /**
     * Create a Journal Entry directly, optionally saved in the past (so already Locked), with a
     * Comment, and optionally with the Journal submitted.
     *
     * @param array $data Keys: cmid, userid, text, model, minutesago, comment, commenterid, submitted.
     * @return stdClass The Entry record.
     */
    public function create_entry(array $data): stdClass {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($data['cmid'], 'assign');
        $assign = new assign(context_module::instance($cm->id), $cm, $course);
        $submission = $assign->get_user_submission($data['userid'], true);
        if ($submission->status === ASSIGN_SUBMISSION_STATUS_NEW) {
            $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
            $DB->update_record('assign_submission', $submission);
        }
        $saved = journal::now() - ((int) ($data['minutesago'] ?? 0)) * MINSECS;
        $number = $DB->count_records(journal::ENTRY_TABLE, ['submission' => $submission->id]) + 1;
        $entry = (object) [
            'assignment' => $assign->get_instance()->id,
            'submission' => $submission->id,
            'userid' => $data['userid'],
            'entrynumber' => $number,
            'model' => $data['model'] ?? 'none',
            'entrytext' => $data['text'] ?? 'Entry text',
            'entryformat' => FORMAT_HTML,
            'timecreated' => $saved,
            'timemodified' => $saved,
            'timelocked' => $saved + journal::EDIT_WINDOW <= journal::now() ? $saved + journal::EDIT_WINDOW : null,
        ];
        $entry->id = $DB->insert_record(journal::ENTRY_TABLE, $entry);
        if (!empty($data['comment'])) {
            $DB->insert_record(journal::COMMENT_TABLE, (object) [
                'entry' => $entry->id,
                'userid' => $data['commenterid'],
                'commenttext' => $data['comment'],
                'commentformat' => FORMAT_HTML,
                'timecreated' => $saved,
                'timemodified' => $saved,
                'timelocked' => $saved + journal::EDIT_WINDOW,
            ]);
        }
        if (!empty($data['submitted'])) {
            (new journal($assign, $assign->get_user_submission($data['userid'], false)))->submit();
        }
        return $entry;
    }
}
