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

namespace assignsubmission_reflectivejournal\local;

/**
 * Journal Submission at the deadline.
 *
 * A Journal's deadline is the latest of the due date, the cut-off date and the student's extension,
 * with any user or group override applied. Journals are submitted when their deadline falls inside
 * the window being processed, so a Journal reopened after its deadline (by reverting to draft) is
 * not immediately submitted again; a new extension gives it a new deadline.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deadlines {
    /**
     * The deadline for a student's Journal.
     *
     * @param \assign $assign The Assignment.
     * @param int $userid The student.
     * @return int Timestamp, or 0 if the Assignment has no dates.
     */
    public static function for_user(\assign $assign, int $userid): int {
        $instance = $assign->get_instance($userid);
        $flags = $assign->get_user_flags($userid, false);
        return max((int) $instance->duedate, (int) $instance->cutoffdate, (int) ($flags->extensionduedate ?? 0));
    }

    /**
     * Submit every open Journal whose deadline is after $from and no later than $to.
     *
     * @param int $from Start of the window (exclusive), usually the previous run.
     * @param int $to End of the window (inclusive), usually now.
     * @return int Number of Journals submitted.
     */
    public static function submit_due(int $from, int $to): int {
        global $DB;
        $count = 0;
        $assignmentids = $DB->get_fieldset_select(
            'assign_plugin_config',
            'assignment',
            "plugin = 'reflectivejournal' AND subtype = 'assignsubmission' AND name = 'enabled' AND value = '1'"
        );
        foreach ($assignmentids as $assignmentid) {
            $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $assign = new \assign(\context_module::instance($cm->id), $cm, null);
            $submissions = $DB->get_records_select(
                'assign_submission',
                'assignment = :assignment AND latest = 1 AND userid > 0 AND status <> :submitted',
                ['assignment' => $assignmentid, 'submitted' => ASSIGN_SUBMISSION_STATUS_SUBMITTED]
            );
            foreach ($submissions as $submission) {
                $deadline = self::for_user($assign, (int) $submission->userid);
                if ($deadline <= $from || $deadline > $to) {
                    continue;
                }
                $journal = new journal($assign, $submission);
                if ($journal->count_entries() === 0) {
                    continue;
                }
                $journal->submit();
                $count++;
            }
        }
        return $count;
    }
}
