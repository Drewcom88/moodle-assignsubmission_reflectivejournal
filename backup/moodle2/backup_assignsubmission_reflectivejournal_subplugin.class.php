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
 * Backup of Reflective journal Entries and Comments.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_assignsubmission_reflectivejournal_subplugin extends backup_subplugin {
    /**
     * Structure for each submission: its Entries, each with its Comment.
     *
     * @return backup_subplugin_element
     */
    protected function define_submission_subplugin_structure() {
        $subplugin = $this->get_subplugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $entries = new backup_nested_element('rjentries');
        $entry = new backup_nested_element('rjentry', ['id'], [
            'submission', 'userid', 'entrynumber', 'model', 'entrytext', 'entryformat', 'timecreated', 'timemodified', 'timelocked',
        ]);
        $comment = new backup_nested_element('rjcomment', ['id'], [
            'entry', 'userid', 'commenttext', 'commentformat', 'timecreated', 'timemodified', 'timelocked',
        ]);

        $subplugin->add_child($wrapper);
        $wrapper->add_child($entries);
        $entries->add_child($entry);
        $entry->add_child($comment);

        $entry->set_source_table(
            'assignsubmission_reflectivejournal_entry',
            ['submission' => backup::VAR_PARENTID],
            'entrynumber ASC'
        );
        $comment->set_source_table('assignsubmission_reflectivejournal_comment', ['entry' => backup::VAR_PARENTID]);
        $entry->annotate_ids('user', 'userid');
        $comment->annotate_ids('user', 'userid');
        return $subplugin;
    }
}
