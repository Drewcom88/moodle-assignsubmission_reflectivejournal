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
 * Reacts to Assignment events.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /**
     * A new extension reopens a submitted Journal until its new deadline, when it is submitted again.
     *
     * @param \mod_assign\event\extension_granted $event The event.
     */
    public static function extension_granted(\mod_assign\event\extension_granted $event): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = get_coursemodule_from_id('assign', $event->contextinstanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }
        $assign = new \assign(\context_module::instance($cm->id), $cm, null);
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');
        if (!$plugin || !$plugin->is_enabled()) {
            return;
        }
        $userid = (int) $event->relateduserid;
        $journal = journal::for_user($assign, $userid);
        if (!$journal || !$journal->is_submitted() || deadlines::for_user($assign, $userid) <= journal::now()) {
            return;
        }
        $submission = $journal->get_submission();
        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $DB->update_record('assign_submission', $submission);
    }
}
