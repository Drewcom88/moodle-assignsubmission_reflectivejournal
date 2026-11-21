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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

/**
 * Shared set-up for Reflective journal tests.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class journal_testcase extends \advanced_testcase {
    use \mod_assign_test_generator;

    /** @var int A fixed start time for tests: 10:00 UTC on 1 March 2027. */
    protected const START = 1803808800;

    /** @var \core\clock|\frozen_clock The test clock. */
    protected $clock;

    /** @var \stdClass Course. */
    protected $course;

    /** @var \stdClass Student. */
    protected $student;

    /** @var \stdClass Editing teacher. */
    protected $teacher;

    /**
     * Freeze the clock and create a course with a student and a teacher.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->clock = $this->mock_clock_with_frozen(self::START);
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Create an Assignment with the Reflective journal enabled.
     *
     * @param array $params Assignment settings, overriding the defaults.
     * @return \mod_assign_testable_assign
     */
    protected function create_journal_assign(array $params = []): \assign {
        return $this->create_instance($this->course, $params + [
            'assignsubmission_reflectivejournal_enabled' => 1,
            'assignsubmission_onlinetext_enabled' => 0,
            'assignsubmission_file_enabled' => 0,
            'submissiondrafts' => 1,
            'duedate' => self::START + WEEKSECS,
        ]);
    }

    /**
     * The student's Journal, creating the submission if needed.
     *
     * @param \assign $assign The Assignment.
     * @param \stdClass|null $student The student (defaults to the course student).
     * @return journal
     */
    protected function journal(\assign $assign, ?\stdClass $student = null): journal {
        return journal::for_user($assign, (int) ($student ?? $this->student)->id, true);
    }

    /**
     * Move the test clock forward.
     *
     * @param int $seconds Seconds to move.
     */
    protected function wait(int $seconds): void {
        $this->clock->bump($seconds);
    }

    /**
     * Allocate a marker to a student, on Moodle 4.5 (one marker) or 5.3 and later (several markers).
     *
     * @param \assign $assign The Assignment, with marking workflow and allocation on.
     * @param \stdClass $student The student.
     * @param \stdClass $marker The marker.
     */
    protected function allocate(\assign $assign, \stdClass $student, \stdClass $marker): void {
        global $DB;
        if ($DB->get_manager()->table_exists('assign_allocated_marker')) {
            $DB->insert_record('assign_allocated_marker', (object) [
                'assignment' => $assign->get_instance()->id,
                'student' => $student->id,
                'marker' => $marker->id,
                'optional' => 0,
            ]);
            return;
        }
        $flags = $assign->get_user_flags($student->id, true);
        $flags->allocatedmarker = $marker->id;
        $assign->update_user_flags($flags);
    }

    /**
     * Create an Assignment that uses marking workflow with marker allocation.
     *
     * @param array $params Extra settings.
     * @return \assign
     */
    protected function create_allocated_journal_assign(array $params = []): \assign {
        return $this->create_journal_assign($params + ['markingworkflow' => 1, 'markingallocation' => 1]);
    }
}
