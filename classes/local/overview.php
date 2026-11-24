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
 * The tutor's overview of their students' Journals.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class overview {
    /**
     * One row per student the viewer may see: their Locked Entries, how many are waiting for a
     * Comment, and whether the Journal has been submitted. Students with Entries waiting come first.
     *
     * @param \assign $assign The Assignment.
     * @param int $viewerid The tutor.
     * @param int $groupid Only this group (0 for all the viewer may see).
     * @return stdClass[] Rows with studentid, name, entries, awaiting, submitted.
     */
    public static function rows(\assign $assign, int $viewerid, int $groupid = 0): array {
        $rows = [];
        foreach ($assign->list_participants($groupid, false) as $student) {
            $studentid = (int) $student->id;
            if (!tutors::can_view($assign, $studentid, $viewerid)) {
                continue;
            }
            $journal = journal::for_user($assign, $studentid);
            $rows[] = (object) [
                'studentid' => $studentid,
                'name' => $assign->is_blind_marking()
                    ? get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($studentid)
                    : fullname($student),
                'entries' => $journal ? count($journal->entries_visible_to($viewerid)) : 0,
                'awaiting' => $journal ? $journal->count_awaiting_comment() : 0,
                'submitted' => $journal ? $journal->is_submitted() : false,
            ];
        }
        usort($rows, fn($a, $b) => ($b->awaiting > 0) <=> ($a->awaiting > 0));
        return $rows;
    }
}
