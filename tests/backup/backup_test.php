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

namespace assignsubmission_reflectivejournal\backup;

use assignsubmission_reflectivejournal\local\journal;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/../journal_testcase.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_assignsubmission_reflectivejournal_subplugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_assignsubmission_reflectivejournal_subplugin::class)]
/**
 * Backup and restore tests for Reflective journal Entries and Comments.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_assignsubmission_reflectivejournal_subplugin
 * @covers     \restore_assignsubmission_reflectivejournal_subplugin
 */
final class backup_test extends \assignsubmission_reflectivejournal\journal_testcase {
    public function test_a_course_backup_with_user_data_restores_entries_and_comments(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 9]);
        $journal = $this->journal($assign);
        $entry = $journal->save_entry('<p>Week 1</p>', FORMAT_HTML, 'kolb');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entry->id, (int) $this->teacher->id, '<p>Good</p>', FORMAT_HTML);
        $this->wait(journal::EDIT_WINDOW);

        $restored = $this->backup_and_restore(true);

        $entries = \assignsubmission_reflectivejournal\local\journal::for_user($restored, (int) $this->student->id)
            ->entries_visible_to((int) $this->student->id);
        $this->assertCount(1, $entries);
        $this->assertSame('<p>Week 1</p>', $entries[0]->text);
        $this->assertSame('kolb', $entries[0]->model);
        $this->assertSame(self::START, $entries[0]->timemodified);
        $this->assertTrue($entries[0]->locked);
        $this->assertSame('<p>Good</p>', $entries[0]->comment->text);
        $this->assertSame((int) $this->teacher->id, $entries[0]->comment->authorid);
        $this->assertSame(9, $restored->get_submission_plugin_by_type('reflectivejournal')
            ->get_journal_settings()->maxentries);
    }

    public function test_a_backup_without_user_data_restores_only_the_settings(): void {
        global $DB;
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 4]);
        $this->journal($assign)->save_entry('<p>Week 1</p>', FORMAT_HTML, 'none');
        $before = $DB->count_records(journal::ENTRY_TABLE);

        $restored = $this->backup_and_restore(false);

        $this->assertSame($before, $DB->count_records(journal::ENTRY_TABLE), 'No entries were copied.');
        $this->assertSame(4, $restored->get_submission_plugin_by_type('reflectivejournal')
            ->get_journal_settings()->maxentries);
    }

    public function test_course_reset_deleting_submissions_removes_entries(): void {
        global $DB;
        $assign = $this->create_journal_assign();
        $this->journal($assign)->save_entry('<p>Week 1</p>', FORMAT_HTML, 'none');

        $assign->reset_userdata((object) ['reset_assign_submissions' => 1, 'courseid' => $this->course->id,
            'timeshift' => 0, 'reset_assign_user_overrides' => 0, 'reset_assign_group_overrides' => 0]);

        $this->assertSame(0, $DB->count_records(journal::ENTRY_TABLE));
    }

    /**
     * Back up the course and restore it as a new course.
     *
     * @param bool $userdata Include user data.
     * @return \assign The restored Assignment.
     */
    private function backup_and_restore(bool $userdata): \assign {
        global $USER, $CFG;
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value($userdata);
        $bc->execute_plan();
        $backupid = $bc->get_backupid();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'R1', $this->course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value($userdata);
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        $cms = get_fast_modinfo($newcourseid)->get_instances_of('assign');
        $cm = reset($cms);
        return new \assign(\context_module::instance($cm->id), $cm, null);
    }
}
