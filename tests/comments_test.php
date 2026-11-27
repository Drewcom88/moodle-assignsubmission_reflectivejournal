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

use assignsubmission_reflectivejournal\local\journal;
use assignsubmission_reflectivejournal\local\locker;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\journal::class)]
/**
 * Tests for Tutor Comments on Entries.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\journal
 */
final class comments_test extends journal_testcase {
    /** @var \assign The Assignment. */
    private \assign $assign;

    /** @var journal The student's Journal. */
    private journal $journal;

    /** @var \stdClass The student's Tutor. */
    private \stdClass $tutor;

    /** @var int Entry 1, already Locked. */
    private int $entryid;

    protected function setUp(): void {
        parent::setUp();
        $this->tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->assign = $this->create_allocated_journal_assign();
        $this->allocate($this->assign, $this->student, $this->tutor);
        $this->journal = $this->journal($this->assign);
        $this->entryid = $this->journal->save_entry('Week 1', FORMAT_HTML, 'none')->id;
        $this->wait(journal::EDIT_WINDOW);
    }

    public function test_the_tutor_can_comment_on_a_locked_entry(): void {
        $comment = $this->journal->save_comment($this->entryid, (int) $this->tutor->id, 'Good start', FORMAT_HTML);

        $this->assertSame('Good start', $comment->text);
        $this->assertSame((int) $this->tutor->id, $comment->authorid);
    }

    public function test_no_one_can_comment_on_an_entry_still_in_its_edit_window(): void {
        $entry = $this->journal->save_entry('Week 2', FORMAT_HTML, 'none');

        $this->assertFalse($this->journal->can_comment($entry->id, (int) $this->tutor->id));
    }

    public function test_the_student_cannot_see_a_comment_until_it_locks(): void {
        $this->journal->save_comment($this->entryid, (int) $this->tutor->id, 'Good start', FORMAT_HTML);

        $this->assertNull($this->student_view()->comment);
        $this->assertNotNull(
            $this->journal->find_entry($this->entryid, (int) $this->tutor->id)->comment,
            'The tutor sees their own comment straight away.'
        );

        $this->wait(journal::EDIT_WINDOW);
        $this->assertSame('Good start', $this->student_view()->comment->text);
    }

    public function test_the_tutor_can_edit_their_comment_until_15_minutes_after_the_last_save(): void {
        $tutorid = (int) $this->tutor->id;
        $this->journal->save_comment($this->entryid, $tutorid, 'Draft', FORMAT_HTML);
        $this->wait(10 * MINSECS);
        $this->journal->save_comment($this->entryid, $tutorid, 'Better', FORMAT_HTML);
        $this->wait(14 * MINSECS);

        $this->assertTrue($this->journal->can_comment($this->entryid, $tutorid));
        $this->wait(MINSECS);
        $this->assertFalse($this->journal->can_comment($this->entryid, $tutorid), 'Locked comments cannot change.');
        $this->assertSame('Better', $this->student_view()->comment->text);
    }

    public function test_an_entry_can_have_only_one_comment(): void {
        $this->journal->save_comment($this->entryid, (int) $this->tutor->id, 'Mine', FORMAT_HTML);

        $this->assertFalse(
            $this->journal->can_comment($this->entryid, (int) $this->teacher->id),
            'Another member of staff cannot add a second comment, even while the first is open.'
        );
        $this->assertSame((int) $this->tutor->id, $this->journal->comment_in_progress_by($this->entryid));
    }

    public function test_the_student_is_notified_once_when_a_comment_locks(): void {
        $this->journal->save_comment($this->entryid, (int) $this->tutor->id, 'Good start', FORMAT_HTML);
        $sink = $this->redirectMessages();
        $this->wait(journal::EDIT_WINDOW);

        locker::process(journal::now());
        locker::process(journal::now());

        $tostudent = array_filter($sink->get_messages(), fn($m) => $m->eventtype === 'commentlocked');
        $this->assertCount(1, $tostudent);
        $this->assertEquals($this->student->id, reset($tostudent)->useridto);
    }

    public function test_a_marker_who_is_not_the_tutor_cannot_comment(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');

        $this->expectException(\moodle_exception::class);
        $this->journal->save_comment($this->entryid, (int) $other->id, 'Not mine', FORMAT_HTML);
    }

    public function test_entries_waiting_for_a_comment_are_locked_entries_without_a_locked_comment(): void {
        $this->assertSame(1, $this->journal->count_awaiting_comment());

        $this->journal->save_entry('Week 2', FORMAT_HTML, 'none');
        $this->assertSame(1, $this->journal->count_awaiting_comment(), 'Entry 2 is still in its Edit Window.');

        $this->wait(journal::EDIT_WINDOW);
        $this->assertSame(2, $this->journal->count_awaiting_comment());

        $this->journal->save_comment($this->entryid, (int) $this->tutor->id, 'Good start', FORMAT_HTML);
        $this->assertSame(2, $this->journal->count_awaiting_comment(), 'A comment still being written does not count.');

        $this->wait(journal::EDIT_WINDOW);
        $this->assertSame(1, $this->journal->count_awaiting_comment());
    }

    public function test_no_comment_is_not_shown_where_a_comment_box_is_offered(): void {
        $nocomment = get_string('nocomment', 'assignsubmission_reflectivejournal');

        $withbox = \assignsubmission_reflectivejournal\local\presenter::journal(
            $this->journal,
            (int) $this->tutor->id,
            false,
            fn() => '<form>comment box</form>'
        );
        $readonly = \assignsubmission_reflectivejournal\local\presenter::journal($this->journal, (int) $this->tutor->id);

        $this->assertStringNotContainsString($nocomment, $withbox);
        $this->assertStringContainsString($nocomment, $readonly);
    }

    /**
     * Entry 1 as the student sees it.
     *
     * @return \assignsubmission_reflectivejournal\local\entry
     */
    private function student_view(): \assignsubmission_reflectivejournal\local\entry {
        return $this->journal->find_entry($this->entryid, (int) $this->student->id);
    }
}
