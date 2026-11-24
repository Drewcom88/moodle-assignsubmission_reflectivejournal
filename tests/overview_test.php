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
use assignsubmission_reflectivejournal\local\overview;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\overview::class)]
/**
 * Tests for the tutor's overview of their students' Journals.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\overview
 */
final class overview_test extends journal_testcase {
    public function test_each_student_is_listed_with_their_entries_and_those_waiting_for_a_comment(): void {
        $quiet = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['firstname' => 'Quiet']);
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_entry('Week 2', FORMAT_HTML, 'none');

        $rows = $this->rows_by_student(overview::rows($assign, (int) $this->teacher->id));

        $this->assertSame(1, $rows[$this->student->id]->entries, 'Entry 2 is still in its Edit Window.');
        $this->assertSame(1, $rows[$this->student->id]->awaiting);
        $this->assertFalse($rows[$this->student->id]->submitted);
        $this->assertSame(0, $rows[$quiet->id]->entries, 'Students who have not started are listed too.');
        $this->assertArrayNotHasKey($this->teacher->id, $rows, 'Staff are not listed.');
    }

    public function test_a_marker_sees_only_their_own_students_and_students_with_no_marker(): void {
        $tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $othertutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $theirs = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $unallocated = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $assign = $this->create_allocated_journal_assign();
        $this->allocate($assign, $this->student, $tutor);
        $this->allocate($assign, $theirs, $othertutor);

        $ids = array_keys($this->rows_by_student(overview::rows($assign, (int) $tutor->id)));

        sort($ids);
        $this->assertSame([(int) $this->student->id, (int) $unallocated->id], $ids);
        $this->assertCount(3, overview::rows($assign, (int) $this->teacher->id), 'Editing teachers see every student.');
    }

    public function test_students_with_entries_waiting_come_first(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['lastname' => 'Aaron']);
        $assign = $this->create_journal_assign();
        $this->journal($assign)->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);

        $rows = overview::rows($assign, (int) $this->teacher->id);

        $this->assertSame((int) $this->student->id, $rows[0]->studentid);
        $this->assertSame((int) $other->id, $rows[1]->studentid);
    }

    /**
     * Index overview rows by student id.
     *
     * @param array $rows Rows.
     * @return array
     */
    private function rows_by_student(array $rows): array {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row->studentid] = $row;
        }
        return $indexed;
    }
}
