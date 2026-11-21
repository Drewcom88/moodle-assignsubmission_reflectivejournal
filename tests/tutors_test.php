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
use assignsubmission_reflectivejournal\local\tutors;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\tutors::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\locker::class)]
/**
 * Tests for finding a student's Tutor and notifying them when an Entry locks.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\tutors
 * @covers     \assignsubmission_reflectivejournal\local\locker
 */
final class tutors_test extends journal_testcase {
    public function test_the_tutor_is_the_allocated_marker(): void {
        $tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $tutor);

        $this->assertSame([(int) $tutor->id], tutors::for_student($assign, (int) $this->student->id));
    }

    public function test_with_several_markers_the_first_allocated_is_the_tutor(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('assign_allocated_marker')) {
            $this->markTestSkipped('Multiple markers per student need Moodle 5.3 or later.');
        }
        $first = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $second = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $first);
        $this->allocate($assign, $this->student, $second);

        $this->assertSame([(int) $first->id], tutors::for_student($assign, (int) $this->student->id));
    }

    public function test_with_no_allocated_marker_everyone_who_can_grade_is_the_tutor(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();

        $tutors = tutors::for_student($assign, (int) $this->student->id);

        sort($tutors);
        $this->assertSame([(int) $this->teacher->id, (int) $other->id], $tutors);
    }

    public function test_with_allocation_off_everyone_who_can_grade_is_the_tutor(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_journal_assign();

        $tutors = tutors::for_student($assign, (int) $this->student->id);

        sort($tutors);
        $this->assertSame([(int) $this->teacher->id, (int) $other->id], $tutors);
    }

    public function test_the_tutor_is_notified_when_an_entry_locks(): void {
        $tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $tutor);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $sink = $this->redirectMessages();

        locker::process(journal::now());
        $this->assertCount(0, $sink->get_messages(), 'Nothing is sent during the Edit Window.');

        $this->wait(journal::EDIT_WINDOW);
        locker::process(journal::now());
        locker::process(journal::now());

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages, 'Exactly one notification, however often the task runs.');
        $this->assertEquals($tutor->id, $messages[0]->useridto);
        $this->assertSame('entrylocked', $messages[0]->eventtype);
    }

    public function test_after_reallocation_the_new_tutor_is_notified(): void {
        $old = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $new = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $old);
        $this->reallocate($assign, $this->student, $new);
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $sink = $this->redirectMessages();

        $this->wait(journal::EDIT_WINDOW);
        locker::process(journal::now());

        $this->assertSame([(int) $new->id], array_map(fn($m) => (int) $m->useridto, $sink->get_messages()));
    }

    public function test_editing_teachers_can_cover_but_other_markers_cannot_comment(): void {
        $tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $othermarker = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $tutor);
        $studentid = (int) $this->student->id;

        $this->assertTrue(tutors::can_comment($assign, $studentid, (int) $tutor->id));
        $this->assertTrue(tutors::can_comment($assign, $studentid, (int) $this->teacher->id), 'Editing teacher covers.');
        $this->assertFalse(tutors::can_comment($assign, $studentid, (int) $othermarker->id));
        $this->assertFalse(tutors::can_comment($assign, $studentid, $studentid));
    }

    /**
     * Change a student's only marker.
     *
     * @param \assign $assign The Assignment.
     * @param \stdClass $student The student.
     * @param \stdClass $marker The new marker.
     */
    private function reallocate(\assign $assign, \stdClass $student, \stdClass $marker): void {
        global $DB;
        if ($DB->get_manager()->table_exists('assign_allocated_marker')) {
            $DB->set_field(
                'assign_allocated_marker',
                'marker',
                $marker->id,
                ['assignment' => $assign->get_instance()->id, 'student' => $student->id]
            );
            return;
        }
        $this->allocate($assign, $student, $marker);
    }
}
