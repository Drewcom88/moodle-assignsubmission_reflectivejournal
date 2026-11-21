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

namespace assignsubmission_reflectivejournal;

use assignsubmission_reflectivejournal\local\deadlines;
use assignsubmission_reflectivejournal\local\journal;
use assignsubmission_reflectivejournal\task\submit_journals;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/journal_testcase.php');

/**
 * Tests for Journal Submission and reopening a Journal.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\deadlines
 * @covers     \assignsubmission_reflectivejournal\task\submit_journals
 * @covers     \assign_submission_reflectivejournal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\deadlines::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\task\submit_journals::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_reflectivejournal::class)]
final class submission_test extends journal_testcase {
    public function test_the_deadline_is_the_latest_of_due_date_cut_off_and_extension(): void {
        $due = self::START + DAYSECS;
        $cutoff = self::START + 2 * DAYSECS;
        $assign = $this->create_journal_assign(['duedate' => $due, 'cutoffdate' => $cutoff]);
        $studentid = (int) $this->student->id;

        $this->assertSame($cutoff, deadlines::for_user($assign, $studentid));

        $this->setAdminUser();
        $assign->save_user_extension($studentid, self::START + 3 * DAYSECS);
        $this->assertSame(self::START + 3 * DAYSECS, deadlines::for_user($assign, $studentid));
    }

    public function test_the_journal_is_submitted_when_the_deadline_passes(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + DAYSECS]);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');

        $this->assertSame(0, deadlines::submit_due(self::START, self::START + DAYSECS - 1));
        $this->assertSame(1, deadlines::submit_due(self::START + DAYSECS - 1, self::START + DAYSECS));

        $this->assertTrue($this->journal($assign)->is_submitted());
    }

    public function test_the_scheduled_task_submits_journals_whose_deadline_passed_since_it_last_ran(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + HOURSECS]);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $task = new submit_journals();
        $task->set_last_run_time(self::START);
        $this->wait(2 * HOURSECS);

        $task->execute();

        $this->assertTrue($this->journal($assign)->is_submitted());
    }

    public function test_an_entry_still_in_its_edit_window_is_locked_and_kept_at_submission(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + 5 * MINSECS]);
        $this->journal($assign)->save_entry('Written just before the deadline', FORMAT_HTML, 'none');
        $this->wait(5 * MINSECS);

        deadlines::submit_due(self::START, journal::now());

        $entries = $this->journal($assign)->entries_visible_to((int) $this->teacher->id);
        $this->assertCount(1, $entries, 'The tutor can now see it.');
        $this->assertTrue($entries[0]->locked);
        $this->assertSame(self::START, $entries[0]->timemodified, 'It keeps the time it was last saved.');
    }

    public function test_a_comment_still_in_its_edit_window_is_locked_and_kept_at_submission(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + HOURSECS]);
        $journal = $this->journal($assign);
        $entryid = $journal->save_entry('Week 1', FORMAT_HTML, 'none')->id;
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entryid, (int) $this->teacher->id, 'Last-minute comment', FORMAT_HTML);
        $this->wait(HOURSECS - journal::EDIT_WINDOW);

        deadlines::submit_due(self::START, journal::now());

        $this->assertSame(
            'Last-minute comment',
            $this->journal($assign)->find_entry($entryid, (int) $this->student->id)->comment->text
        );
    }

    public function test_after_submission_no_entries_or_comments_can_be_added(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + HOURSECS]);
        $entryid = $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none')->id;
        $this->wait(HOURSECS);
        deadlines::submit_due(self::START, journal::now());

        $journal = $this->journal($assign);
        $this->assertFalse($journal->can_start_entry());
        $this->assertFalse($journal->can_comment($entryid, (int) $this->teacher->id));
    }

    public function test_reaching_the_maximum_does_not_submit_the_journal(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 1]);
        $this->journal($assign)->save_entry('Only entry', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);

        deadlines::submit_due(self::START, journal::now());

        $this->assertFalse($this->journal($assign)->is_submitted());
    }

    public function test_in_deadline_only_mode_the_student_cannot_submit_early(): void {
        $assign = $this->create_journal_assign();
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');

        $result = $plugin->precheck_submission($this->journal($assign)->get_submission());

        $this->assertSame(get_string('submitatdeadline', 'assignsubmission_reflectivejournal'), $result);
    }

    public function test_in_button_mode_submitting_early_locks_the_open_entry(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_submissionmode' => 'button']);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');
        $this->assertTrue($plugin->precheck_submission($this->journal($assign)->get_submission()));

        $this->submit_for_grading($this->student, $assign);

        $journal = $this->journal($assign);
        $this->assertTrue($journal->is_submitted());
        $this->assertCount(1, $journal->entries_visible_to((int) $this->teacher->id));
    }

    public function test_reverting_to_draft_after_the_deadline_lets_the_student_continue(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + HOURSECS]);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(HOURSECS);
        deadlines::submit_due(self::START, journal::now());
        $this->setAdminUser();

        $assign->revert_to_draft($this->student->id);
        deadlines::submit_due(journal::now(), journal::now() + MINSECS);

        $journal = $this->journal($assign);
        $this->assertFalse($journal->is_submitted(), 'Not submitted again straight away.');
        $this->assertSame(2, $journal->save_entry('Week 2', FORMAT_HTML, 'none')->number);
    }

    public function test_a_new_extension_reopens_the_journal_until_the_new_date(): void {
        $assign = $this->create_journal_assign(['duedate' => self::START + HOURSECS]);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(HOURSECS);
        deadlines::submit_due(self::START, journal::now());
        $this->setAdminUser();

        $assign->save_user_extension($this->student->id, journal::now() + DAYSECS);
        $this->assertFalse($this->journal($assign)->is_submitted(), 'The new extension reopens the journal.');
        $this->assertSame(0, deadlines::submit_due(journal::now(), journal::now() + HOURSECS));

        $this->assertSame(1, deadlines::submit_due(journal::now(), journal::now() + DAYSECS));
    }

    public function test_a_new_attempt_carries_over_entries_and_comments(): void {
        $assign = $this->create_journal_assign([
            'duedate' => self::START + HOURSECS,
            'maxattempts' => 2,
            'attemptreopenmethod' => 'manual',
        ]);
        $journal = $this->journal($assign);
        $entryid = $journal->save_entry('Week 1', FORMAT_HTML, 'none')->id;
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entryid, (int) $this->teacher->id, 'Nice', FORMAT_HTML);
        $this->wait(HOURSECS);
        deadlines::submit_due(self::START, journal::now());
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        $assign->testable_process_add_attempt($this->student->id);

        $newjournal = $this->journal($assign);
        $this->assertNotEquals($journal->get_submission()->id, $newjournal->get_submission()->id);
        $entries = $newjournal->entries_visible_to((int) $this->student->id);
        $this->assertCount(1, $entries);
        $this->assertTrue($entries[0]->locked);
        $this->assertSame(self::START, $entries[0]->timemodified);
        $this->assertSame('Nice', $entries[0]->comment->text);
        $this->assertSame(2, $newjournal->save_entry('Week 2', FORMAT_HTML, 'none')->number);
    }
}
