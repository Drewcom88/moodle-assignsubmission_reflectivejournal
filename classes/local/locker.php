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

use stdClass;

/**
 * Locks Entries and Comments whose Edit Window has closed, and sends the matching notifications.
 *
 * Visibility never waits for this: an item counts as Locked as soon as its Edit Window has closed
 * (see journal::is_locked). This records the lock permanently and sends the notification once.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class locker {
    /**
     * Lock everything whose Edit Window closed at or before $now.
     *
     * @param int $now Current time.
     * @return int Number of items locked.
     */
    public static function process(int $now): int {
        global $DB;
        $cutoff = $now - journal::EDIT_WINDOW;
        $count = 0;
        $assigns = [];

        $entries = $DB->get_records_select(
            journal::ENTRY_TABLE,
            'timelocked IS NULL AND timemodified <= ?',
            [$cutoff],
            'id ASC'
        );
        foreach ($entries as $entry) {
            $assign = self::assign_for($entry->assignment, $assigns);
            if ($assign) {
                self::lock_entry($assign, $entry, $entry->timemodified + journal::EDIT_WINDOW);
                $count++;
            }
        }

        $sql = "SELECT c.*, e.assignment
                  FROM {" . journal::COMMENT_TABLE . "} c
                  JOIN {" . journal::ENTRY_TABLE . "} e ON e.id = c.entry
                 WHERE c.timelocked IS NULL AND c.timemodified <= ?
              ORDER BY c.id ASC";
        foreach ($DB->get_records_sql($sql, [$cutoff]) as $comment) {
            $assign = self::assign_for($comment->assignment, $assigns);
            if ($assign) {
                unset($comment->assignment);
                self::lock_comment($assign, $comment, $comment->timemodified + journal::EDIT_WINDOW);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Lock one Entry and notify the student's Tutor.
     *
     * @param \assign $assign The Assignment.
     * @param stdClass $entry Entry record.
     * @param int $time Lock time.
     */
    public static function lock_entry(\assign $assign, stdClass $entry, int $time): void {
        global $DB;
        // Only the first process to lock it sends the notification.
        $DB->set_field_select(journal::ENTRY_TABLE, 'timelocked', $time, 'id = ? AND timelocked IS NULL', [$entry->id]);
        if ($DB->get_field(journal::ENTRY_TABLE, 'timelocked', ['id' => $entry->id]) != $time) {
            return;
        }
        notifier::entry_locked($assign, $entry);
    }

    /**
     * Lock one Comment and notify the student.
     *
     * @param \assign $assign The Assignment.
     * @param stdClass $comment Comment record.
     * @param int $time Lock time.
     */
    public static function lock_comment(\assign $assign, stdClass $comment, int $time): void {
        global $DB;
        $DB->set_field_select(
            journal::COMMENT_TABLE,
            'timelocked',
            $time,
            'id = ? AND timelocked IS NULL',
            [$comment->id]
        );
        if ($DB->get_field(journal::COMMENT_TABLE, 'timelocked', ['id' => $comment->id]) != $time) {
            return;
        }
        $entry = $DB->get_record(journal::ENTRY_TABLE, ['id' => $comment->entry], '*', MUST_EXIST);
        notifier::comment_locked($assign, $entry, $comment);
    }

    /**
     * Load (and cache) the assign object for an assignment id.
     *
     * @param int $assignmentid Assignment id.
     * @param array $cache Cache of loaded objects.
     * @return \assign|null Null if the activity no longer exists.
     */
    private static function assign_for(int $assignmentid, array &$cache): ?\assign {
        if (!array_key_exists($assignmentid, $cache)) {
            $cm = get_coursemodule_from_instance('assign', $assignmentid, 0, false, IGNORE_MISSING);
            $cache[$assignmentid] = $cm ? new \assign(\context_module::instance($cm->id), $cm, null) : null;
        }
        return $cache[$assignmentid];
    }
}
