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
use assignsubmission_reflectivejournal\local\presenter;
use assignsubmission_reflectivejournal\local\tutors;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\tutors::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_reflectivejournal::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\presenter::class)]
/**
 * Security tests: who may see and change Journals, and what is escaped.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\tutors
 * @covers     \assign_submission_reflectivejournal
 * @covers     \assignsubmission_reflectivejournal\local\presenter
 */
final class security_test extends journal_testcase {
    public function test_a_tutor_in_another_separate_group_cannot_view_or_comment(): void {
        [$assign, $othertutor] = $this->separate_groups_setup();
        $studentid = (int) $this->student->id;

        $this->setUser($othertutor);

        $this->assertFalse(tutors::can_view($assign, $studentid, (int) $othertutor->id));
        $this->assertFalse(tutors::can_comment($assign, $studentid, (int) $othertutor->id));
    }

    public function test_a_tutor_in_the_same_separate_group_can_view_and_comment(): void {
        [$assign, , $grouptutor] = $this->separate_groups_setup();

        $this->setUser($grouptutor);

        $this->assertTrue(tutors::can_view($assign, (int) $this->student->id, (int) $grouptutor->id));
        $this->assertTrue(tutors::can_comment($assign, (int) $this->student->id, (int) $grouptutor->id));
    }

    public function test_entry_notifications_do_not_go_to_graders_in_other_separate_groups(): void {
        [$assign, $othertutor, $grouptutor] = $this->separate_groups_setup();

        $tutors = tutors::for_student($assign, (int) $this->student->id);

        $this->assertContains((int) $grouptutor->id, $tutors);
        $this->assertContains((int) $this->teacher->id, $tutors, 'Editing teachers can see all groups.');
        $this->assertNotContains((int) $othertutor->id, $tutors);
    }

    public function test_a_journal_page_cannot_be_opened_for_someone_who_is_not_a_student_here(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $othercourse = $this->getDataGenerator()->create_course();
        $elsewhere = $this->getDataGenerator()->create_and_enrol($othercourse, 'student');
        $assign = $this->create_journal_assign();
        $this->setUser($this->teacher);

        $this->assertFalse(tutors::can_view($assign, (int) $outsider->id, (int) $this->teacher->id));
        $this->assertFalse(tutors::can_view($assign, (int) $elsewhere->id, (int) $this->teacher->id));
        $this->assertFalse(tutors::can_view($assign, (int) $this->teacher->id, (int) $this->teacher->id));
    }

    public function test_a_student_cannot_submit_early_in_deadline_only_mode_by_any_route(): void {
        $assign = $this->create_journal_assign();
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->setUser($this->student);

        // This is what the mod_assign_submit_for_grading web service (used by the Moodle app) calls.
        try {
            $assign->submit_for_grading((object) ['userid' => $this->student->id], []);
        } catch (\moodle_exception $e) {
            $this->assertSame('submitatdeadline', $e->errorcode);
        }

        $this->assertFalse($this->journal($assign)->is_submitted());
    }

    public function test_names_are_escaped_in_comments(): void {
        global $DB;
        $assign = $this->create_journal_assign();
        $DB->set_field('user', 'firstname', '<script>alert(1)</script>', ['id' => $this->teacher->id]);
        $journal = $this->journal($assign);
        $entry = $journal->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entry->id, (int) $this->teacher->id, 'Comment', FORMAT_HTML);
        $this->wait(journal::EDIT_WINDOW);

        $html = presenter::journal($this->journal($assign), (int) $this->student->id);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_script_in_entry_and_comment_text_is_removed(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $entry = $journal->save_entry('<p>Hi</p><script>alert(1)</script><img src=x onerror=alert(2)>', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entry->id, (int) $this->teacher->id, '<a href="javascript:alert(3)">x</a>', FORMAT_HTML);
        $this->wait(journal::EDIT_WINDOW);

        $html = presenter::journal($this->journal($assign), (int) $this->teacher->id);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_a_student_cannot_change_a_locked_entry_or_another_students_journal(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Original', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $this->setUser($other);

        // Posting the edit form for someone else's submission is refused by Assignment itself.
        try {
            $assign->save_submission((object) [
                'userid' => $this->student->id,
                'reflectivejournal_model' => 'none',
                'reflectivejournal_editor' => ['text' => 'Tampered', 'format' => FORMAT_HTML],
            ], $notices);
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermission', $e->errorcode);
        }

        $texts = array_map(fn($e) => $e->text, $this->journal($assign)->entries_visible_to((int) $this->student->id));
        $this->assertSame(['Original'], $texts);
    }

    /**
     * An Assignment in separate groups mode: the student and a non-editing tutor in group A, another
     * non-editing tutor in group B, and the editing teacher in no group.
     *
     * @return array [assign, tutor in group B, tutor in group A]
     */
    private function separate_groups_setup(): array {
        $generator = $this->getDataGenerator();
        $groupa = $generator->create_group(['courseid' => $this->course->id]);
        $groupb = $generator->create_group(['courseid' => $this->course->id]);
        $grouptutor = $generator->create_and_enrol($this->course, 'teacher');
        $othertutor = $generator->create_and_enrol($this->course, 'teacher');
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $this->student->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $grouptutor->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $othertutor->id]);
        $assign = $this->create_journal_assign(['groupmode' => SEPARATEGROUPS]);
        return [$assign, $othertutor, $grouptutor];
    }
}
