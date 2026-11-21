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

use core\clock;
use core\di;
use moodle_exception;
use stdClass;

/**
 * One student's Journal within one Assignment attempt.
 *
 * This is the main domain seam: it owns the rules for Entries, Edit Windows, Comments and what each
 * viewer may see. See CONTEXT.md for the vocabulary.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class journal {
    /** @var int Length of an Edit Window, in seconds. */
    public const EDIT_WINDOW = 15 * MINSECS;

    /** @var string Entry table. */
    public const ENTRY_TABLE = 'assignsubmission_rjentry';

    /** @var string Comment table. */
    public const COMMENT_TABLE = 'assignsubmission_rjcomment';

    /**
     * Constructor.
     *
     * @param \assign $assign The Assignment.
     * @param stdClass $submission The student's assign_submission record for this attempt.
     */
    public function __construct(
        /** @var \assign The Assignment. */
        private readonly \assign $assign,
        /** @var stdClass The student's assign_submission record for this attempt. */
        private stdClass $submission,
    ) {
    }

    /**
     * The Journal for a student's latest attempt.
     *
     * @param \assign $assign The Assignment.
     * @param int $userid The student.
     * @param bool $create Create the submission record if there is none yet.
     * @return self|null Null if there is no submission and $create is false.
     */
    public static function for_user(\assign $assign, int $userid, bool $create = false): ?self {
        $submission = $assign->get_user_submission($userid, $create);
        return $submission ? new self($assign, $submission) : null;
    }

    /**
     * The Assignment this Journal belongs to.
     *
     * @return \assign
     */
    public function get_assign(): \assign {
        return $this->assign;
    }

    /**
     * The submission record for this attempt.
     *
     * @return stdClass
     */
    public function get_submission(): stdClass {
        return $this->submission;
    }

    /**
     * The student whose Journal this is.
     *
     * @return int
     */
    public function get_studentid(): int {
        return (int) $this->submission->userid;
    }

    /**
     * The teacher's settings for this Assignment.
     *
     * @return settings
     */
    public function settings(): settings {
        return settings::from_plugin($this->assign->get_submission_plugin_by_type('reflectivejournal'));
    }

    /**
     * Whether Journal Submission has happened for this attempt.
     *
     * @return bool
     */
    public function is_submitted(): bool {
        return $this->submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    }

    /**
     * Number of Entries saved so far, including one still in its Edit Window.
     *
     * @return int
     */
    public function count_entries(): int {
        global $DB;
        return $DB->count_records(self::ENTRY_TABLE, ['submission' => $this->submission->id]);
    }

    /**
     * The Entries a viewer may see, in order, each with the Comment the viewer may see.
     *
     * The student sees all their own Entries. Everyone else sees only Locked Entries. A Comment is
     * visible to its author at once, and to everyone else once Locked.
     *
     * @param int $viewerid The viewer.
     * @return entry[]
     */
    public function entries_visible_to(int $viewerid): array {
        $isstudent = $viewerid === $this->get_studentid();
        $entries = [];
        foreach ($this->load_entries() as $entry) {
            if (!$isstudent && !$entry->locked) {
                continue;
            }
            $comment = $entry->comment;
            if ($comment && !$comment->locked && $comment->authorid !== $viewerid) {
                $comment = null;
            }
            $entries[] = $comment === $entry->comment ? $entry : $this->with_comment($entry, $comment);
        }
        return $entries;
    }

    /**
     * The Entry still in its Edit Window, if any.
     *
     * @return entry|null
     */
    public function open_entry(): ?entry {
        foreach ($this->load_entries() as $entry) {
            if (!$entry->locked) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * Whether a new, empty Entry can be started now.
     *
     * @return bool
     */
    public function can_start_entry(): bool {
        return !$this->is_submitted()
            && $this->open_entry() === null
            && $this->count_entries() < $this->settings()->maxentries;
    }

    /**
     * Save the student's writing: update the Entry in its Edit Window, or start a new Entry.
     * Each save restarts the Edit Window.
     *
     * @param string $text Entry text.
     * @param int $format Text format.
     * @param string $model Reflective Model.
     * @return entry The saved Entry, as the student sees it.
     */
    public function save_entry(string $text, int $format, string $model): entry {
        global $DB;
        if (!$this->settings()->allows_model($model)) {
            throw new moodle_exception('errormodel', 'assignsubmission_reflectivejournal');
        }
        $now = self::now();
        $open = $this->open_entry();
        if ($open && !$this->is_submitted()) {
            $DB->update_record(self::ENTRY_TABLE, (object) [
                'id' => $open->id,
                'model' => $model,
                'entrytext' => $text,
                'entryformat' => $format,
                'timemodified' => $now,
            ]);
            $id = $open->id;
        } else if ($this->can_start_entry()) {
            $id = $DB->insert_record(self::ENTRY_TABLE, (object) [
                'assignment' => $this->assign->get_instance()->id,
                'submission' => $this->submission->id,
                'userid' => $this->get_studentid(),
                'entrynumber' => $this->count_entries() + 1,
                'model' => $model,
                'entrytext' => $text,
                'entryformat' => $format,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        } else {
            throw new moodle_exception('errorentrynotallowed', 'assignsubmission_reflectivejournal');
        }
        return $this->find_entry($id, $this->get_studentid());
    }

    /**
     * Whether a user may write (or keep editing) the Comment on an Entry now.
     *
     * @param int $entryid The Entry.
     * @param int $userid The user who wants to Comment.
     * @return bool
     */
    public function can_comment(int $entryid, int $userid): bool {
        $entry = $this->find_entry($entryid, $userid);
        if (!$entry || !$entry->locked || $this->is_submitted()) {
            return false;
        }
        $existing = $this->load_comment($entryid);
        if ($existing && ($existing->locked || $existing->authorid !== $userid)) {
            return false;
        }
        return tutors::can_comment($this->assign, $this->get_studentid(), $userid);
    }

    /**
     * The author of a Comment that is still in its Edit Window, for "comment in progress" notices.
     *
     * @param int $entryid The Entry.
     * @return int|null
     */
    public function comment_in_progress_by(int $entryid): ?int {
        $comment = $this->load_comment($entryid);
        return $comment && !$comment->locked ? $comment->authorid : null;
    }

    /**
     * Save a Tutor's Comment on a Locked Entry. Each save restarts the Comment's Edit Window.
     *
     * @param int $entryid The Entry.
     * @param int $authorid The tutor.
     * @param string $text Comment text.
     * @param int $format Text format.
     * @return comment The saved Comment, as its author sees it.
     */
    public function save_comment(int $entryid, int $authorid, string $text, int $format): comment {
        global $DB;
        if (!$this->can_comment($entryid, $authorid)) {
            throw new moodle_exception('errorcommentnotallowed', 'assignsubmission_reflectivejournal');
        }
        $now = self::now();
        $existing = $this->load_comment($entryid);
        if ($existing) {
            $DB->update_record(self::COMMENT_TABLE, (object) [
                'id' => $existing->id,
                'commenttext' => $text,
                'commentformat' => $format,
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record(self::COMMENT_TABLE, (object) [
                'entry' => $entryid,
                'userid' => $authorid,
                'commenttext' => $text,
                'commentformat' => $format,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        return $this->load_comment($entryid);
    }

    /**
     * Lock every Entry and Comment still in its Edit Window, as happens at Journal Submission.
     * Each one is treated exactly as if its Edit Window had closed, including notifications.
     */
    public function lock_open_items(): void {
        global $DB;
        $now = self::now();
        $entries = $DB->get_records_select(self::ENTRY_TABLE, 'submission = ? AND timelocked IS NULL',
            [$this->submission->id]);
        foreach ($entries as $record) {
            locker::lock_entry($this->assign, $record, $now);
        }
        [$insql, $params] = $DB->get_in_or_equal(
            array_keys($DB->get_records(self::ENTRY_TABLE, ['submission' => $this->submission->id], '', 'id')) ?: [0]);
        $comments = $DB->get_records_select(self::COMMENT_TABLE, "entry $insql AND timelocked IS NULL", $params);
        foreach ($comments as $record) {
            locker::lock_comment($this->assign, $record, $now);
        }
    }

    /**
     * Mark this attempt as submitted (Journal Submission), locking anything still open.
     */
    public function submit(): void {
        global $DB;
        if ($this->is_submitted()) {
            return;
        }
        $this->lock_open_items();
        $this->submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $this->submission->timemodified = self::now();
        $DB->update_record('assign_submission', $this->submission);

        $completion = new \completion_info($this->assign->get_course());
        if ($completion->is_enabled($this->assign->get_course_module()) && $this->assign->get_instance()->completionsubmit) {
            $completion->update_state($this->assign->get_course_module(), COMPLETION_COMPLETE, $this->get_studentid());
        }
        \mod_assign\event\assessable_submitted::create_from_submission($this->assign, $this->submission, false)->trigger();
    }

    /**
     * Current time, from Moodle's clock so tests can control it.
     *
     * @return int
     */
    public static function now(): int {
        return di::get(clock::class)->time();
    }

    /**
     * Whether an item saved at $timemodified is Locked.
     *
     * @param stdClass $record Entry or Comment record.
     * @return bool
     */
    public static function is_locked(stdClass $record): bool {
        return $record->timelocked !== null || self::now() >= $record->timemodified + self::EDIT_WINDOW;
    }

    /**
     * Find one Entry of this Journal as a viewer sees it.
     *
     * @param int $entryid The Entry.
     * @param int $viewerid The viewer.
     * @return entry|null
     */
    public function find_entry(int $entryid, int $viewerid): ?entry {
        foreach ($this->entries_visible_to($viewerid) as $entry) {
            if ($entry->id === $entryid) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * All Entries, in order, with their Comments regardless of viewer.
     *
     * @return entry[]
     */
    private function load_entries(): array {
        global $DB;
        $records = $DB->get_records(self::ENTRY_TABLE, ['submission' => $this->submission->id], 'entrynumber ASC');
        $comments = [];
        if ($records) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($records));
            foreach ($DB->get_records_select(self::COMMENT_TABLE, "entry $insql", $params) as $c) {
                $comments[$c->entry] = self::make_comment($c);
            }
        }
        return array_values(array_map(fn($r) => self::make_entry($r, $comments[$r->id] ?? null), $records));
    }

    /**
     * The Comment on an Entry regardless of viewer.
     *
     * @param int $entryid The Entry.
     * @return comment|null
     */
    private function load_comment(int $entryid): ?comment {
        global $DB;
        $record = $DB->get_record(self::COMMENT_TABLE, ['entry' => $entryid]);
        return $record ? self::make_comment($record) : null;
    }

    /**
     * Build an Entry from its record.
     *
     * @param stdClass $r Record.
     * @param comment|null $comment Its Comment.
     * @return entry
     */
    private static function make_entry(stdClass $r, ?comment $comment): entry {
        return new entry((int) $r->id, (int) $r->entrynumber, (int) $r->userid, $r->model, (string) $r->entrytext,
            (int) $r->entryformat, (int) $r->timemodified, self::is_locked($r),
            (int) $r->timemodified + self::EDIT_WINDOW, $comment);
    }

    /**
     * Build a Comment from its record.
     *
     * @param stdClass $r Record.
     * @return comment
     */
    private static function make_comment(stdClass $r): comment {
        return new comment((int) $r->id, (int) $r->entry, (int) $r->userid, (string) $r->commenttext,
            (int) $r->commentformat, (int) $r->timemodified, self::is_locked($r),
            (int) $r->timemodified + self::EDIT_WINDOW);
    }

    /**
     * Copy of an Entry with a different Comment.
     *
     * @param entry $e Entry.
     * @param comment|null $comment Comment.
     * @return entry
     */
    private function with_comment(entry $e, ?comment $comment): entry {
        return new entry($e->id, $e->number, $e->authorid, $e->model, $e->text, $e->format, $e->timemodified,
            $e->locked, $e->editableuntil, $comment);
    }
}
