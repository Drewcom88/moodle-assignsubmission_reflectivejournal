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

/**
 * Restore of Reflective journal Entries and Comments.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_assignsubmission_reflectivejournal_subplugin extends restore_subplugin {
    /**
     * Paths to process for each submission.
     *
     * @return restore_path_element[]
     */
    protected function define_submission_subplugin_structure() {
        $base = $this->get_pathfor('/rjentries/rjentry');
        return [
            new restore_path_element($this->get_namefor('entry'), $base),
            new restore_path_element($this->get_namefor('comment'), $base . '/rjcomment'),
        ];
    }

    /**
     * Restore one Entry.
     *
     * @param array $data Backup data.
     */
    public function process_assignsubmission_reflectivejournal_entry($data) {
        global $DB;
        $data = (object) $data;
        $oldid = $data->id;
        $data->assignment = $this->get_new_parentid('assign');
        $data->submission = $this->get_mappingid('submission', $data->submission);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $newid = $DB->insert_record('assignsubmission_reflectivejournal_entry', $data);
        $this->set_mapping('assignsubmission_reflectivejournal_entry', $oldid, $newid);
    }

    /**
     * Restore one Comment.
     *
     * @param array $data Backup data.
     */
    public function process_assignsubmission_reflectivejournal_comment($data) {
        global $DB;
        $data = (object) $data;
        $data->entry = $this->get_mappingid('assignsubmission_reflectivejournal_entry', $data->entry);
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $DB->insert_record('assignsubmission_reflectivejournal_comment', $data);
    }
}
