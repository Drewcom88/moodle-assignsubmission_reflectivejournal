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
 * Works out who a student's Tutor is (see ADR 0002).
 *
 * The Tutor is the student's first allocated marker. Moodle 4.5 stores one marker per student in
 * assign_user_flags; Moodle 5.3 stores possibly several in assign_allocated_marker. When no marker
 * is allocated, or marking allocation is off, everyone who can grade acts as the Tutor.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tutors {
    /**
     * The user ids who act as Tutor for a student.
     *
     * @param \assign $assign The Assignment.
     * @param int $studentid The student.
     * @return int[]
     */
    public static function for_student(\assign $assign, int $studentid): array {
        $markers = self::allocated_markers($assign, $studentid);
        if ($markers) {
            return [reset($markers)];
        }
        return self::all_graders($assign, $studentid);
    }

    /**
     * Whether a user may write Comments on a student's Journal: the Tutor, or someone who manages
     * marker allocations (editing teachers and managers by default), as Moodle does for cover.
     *
     * @param \assign $assign The Assignment.
     * @param int $studentid The student.
     * @param int $userid The user who wants to Comment.
     * @return bool
     */
    public static function can_comment(\assign $assign, int $studentid, int $userid): bool {
        $context = $assign->get_context();
        if ($userid == $studentid || !has_capability('mod/assign:grade', $context, $userid)) {
            return false;
        }
        if (has_capability('mod/assign:manageallocations', $context, $userid)) {
            return true;
        }
        return in_array($userid, self::for_student($assign, $studentid));
    }

    /**
     * Whether a user may open a student's Journal page. This follows Moodle's own marker-allocation
     * visibility: with allocation on, graders who do not manage allocations only see students
     * allocated to them (or students with no marker yet, for whom every grader is the Tutor).
     *
     * @param \assign $assign The Assignment.
     * @param int $studentid The student.
     * @param int $userid The viewer.
     * @return bool
     */
    public static function can_view(\assign $assign, int $studentid, int $userid): bool {
        $context = $assign->get_context();
        if (!has_capability('mod/assign:grade', $context, $userid)) {
            return false;
        }
        if (!self::allocation_enabled($assign) || has_capability('mod/assign:manageallocations', $context, $userid)) {
            return true;
        }
        $markers = self::allocated_markers($assign, $studentid);
        return !$markers || in_array($userid, $markers);
    }

    /**
     * Whether the Assignment uses marking workflow with marker allocation.
     *
     * @param \assign $assign The Assignment.
     * @return bool
     */
    private static function allocation_enabled(\assign $assign): bool {
        $instance = $assign->get_instance();
        return !empty($instance->markingworkflow) && !empty($instance->markingallocation);
    }

    /**
     * Markers allocated to a student, in allocation order.
     *
     * @param \assign $assign The Assignment.
     * @param int $studentid The student.
     * @return int[]
     */
    private static function allocated_markers(\assign $assign, int $studentid): array {
        global $DB;
        if (!self::allocation_enabled($assign)) {
            return [];
        }
        $assignmentid = $assign->get_instance()->id;
        if ($DB->get_manager()->table_exists('assign_allocated_marker')) {
            // Moodle 5.3 and later: possibly several markers per student. Optional marker slots that are
            // not switched on do not count.
            $records = $DB->get_records_select(
                'assign_allocated_marker',
                'assignment = :assignment AND student = :student AND marker > 0 AND (optional = 0 OR enabled = 1)',
                ['assignment' => $assignmentid, 'student' => $studentid],
                'id ASC',
                'id, marker'
            );
            return array_values(array_map(fn($r) => (int) $r->marker, $records));
        }
        // Moodle 4.5: one marker per student.
        $marker = $DB->get_field(
            'assign_user_flags',
            'allocatedmarker',
            ['assignment' => $assignmentid, 'userid' => $studentid]
        );
        return $marker ? [(int) $marker] : [];
    }

    /**
     * Active users who can grade the Assignment, except the student.
     *
     * @param \assign $assign The Assignment.
     * @param int $studentid The student.
     * @return int[]
     */
    private static function all_graders(\assign $assign, int $studentid): array {
        $users = get_enrolled_users($assign->get_context(), 'mod/assign:grade', 0, 'u.id', 'u.id ASC', 0, 0, true);
        $ids = array_map(fn($u) => (int) $u->id, $users);
        return array_values(array_filter($ids, fn($id) => $id !== $studentid));
    }
}
