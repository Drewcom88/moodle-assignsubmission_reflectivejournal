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

require_once(__DIR__ . '/journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\local\journal::class)]
/**
 * Tests for writing Entries in a Journal.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\local\journal
 */
final class entries_test extends journal_testcase {
    public function test_student_saves_their_first_entry_as_entry_1(): void {
        $assign = $this->create_journal_assign();

        $entry = $this->journal($assign)->save_entry('<p>My first week</p>', FORMAT_HTML, 'gibbs');

        $this->assertSame(1, $entry->number);
        $this->assertSame('<p>My first week</p>', $entry->text);
        $this->assertSame(self::START, $entry->timemodified);
    }

    public function test_saving_again_within_the_edit_window_updates_the_same_entry(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('First draft', FORMAT_HTML, 'gibbs');

        $this->wait(10 * MINSECS);
        $entry = $journal->save_entry('Second draft', FORMAT_HTML, 'kolb');

        $this->assertSame(1, $entry->number);
        $this->assertSame('Second draft', $entry->text);
        $this->assertSame('kolb', $entry->model);
        $this->assertSame(1, $journal->count_entries());
    }

    public function test_each_save_restarts_the_edit_window(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Draft', FORMAT_HTML, 'none');

        $this->wait(10 * MINSECS);
        $entry = $journal->save_entry('Draft again', FORMAT_HTML, 'none');

        $this->assertSame(self::START + 25 * MINSECS, $entry->editableuntil);
        $this->wait(14 * MINSECS);
        $this->assertNotNull($journal->open_entry(), 'Still editable 24 minutes after the first save.');
        $this->wait(MINSECS);
        $this->assertNull($journal->open_entry(), 'Locked 15 minutes after the last save.');
    }

    public function test_staff_cannot_see_an_entry_until_it_is_locked(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Private for now', FORMAT_HTML, 'none');

        $this->assertCount(1, $journal->entries_visible_to((int) $this->student->id));
        $this->assertCount(0, $journal->entries_visible_to((int) $this->teacher->id));

        $this->wait(journal::EDIT_WINDOW);
        $this->assertCount(1, $journal->entries_visible_to((int) $this->teacher->id));
    }

    public function test_a_locked_entry_cannot_be_changed_and_the_next_save_starts_entry_2(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);

        $entry = $journal->save_entry('Week 2', FORMAT_HTML, 'none');

        $this->assertSame(2, $entry->number);
        $entries = $journal->entries_visible_to((int) $this->student->id);
        $this->assertSame('Week 1', $entries[0]->text);
        $this->assertTrue($entries[0]->locked);
    }

    public function test_no_new_entry_can_start_while_one_is_in_its_edit_window(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Week 1', FORMAT_HTML, 'none');

        $this->assertFalse($journal->can_start_entry());
        $this->wait(journal::EDIT_WINDOW);
        $this->assertTrue($journal->can_start_entry());
    }

    public function test_no_new_entry_can_start_once_the_maximum_is_reached(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 2]);
        $journal = $this->journal($assign);
        foreach (['Week 1', 'Week 2'] as $text) {
            $journal->save_entry($text, FORMAT_HTML, 'none');
            $this->wait(journal::EDIT_WINDOW);
        }

        $this->assertFalse($journal->can_start_entry());
        $this->expectException(\moodle_exception::class);
        $journal->save_entry('Week 3', FORMAT_HTML, 'none');
    }

    public function test_lowering_the_maximum_keeps_existing_entries_but_blocks_new_ones(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 3]);
        $journal = $this->journal($assign);
        foreach (['Week 1', 'Week 2', 'Week 3'] as $text) {
            $journal->save_entry($text, FORMAT_HTML, 'none');
            $this->wait(journal::EDIT_WINDOW);
        }

        $assign->get_submission_plugin_by_type('reflectivejournal')->set_config('maxentries', 2);

        $this->assertCount(3, $journal->entries_visible_to((int) $this->student->id));
        $this->assertFalse($journal->can_start_entry());
    }

    public function test_a_model_the_teacher_has_not_allowed_is_refused(): void {
        $assign = $this->create_journal_assign([
            'assignsubmission_reflectivejournal_models' => ['gibbs' => 1, 'driscoll' => 0, 'kolb' => 0, 'none' => 0],
        ]);

        $this->expectException(\moodle_exception::class);
        $this->journal($assign)->save_entry('Text', FORMAT_HTML, 'kolb');
    }

    public function test_each_entry_records_the_model_chosen_for_it(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Week 1', FORMAT_HTML, 'gibbs');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_entry('Week 2', FORMAT_HTML, 'driscoll');

        $models = array_map(fn($e) => $e->model, $journal->entries_visible_to((int) $this->student->id));

        $this->assertSame(['gibbs', 'driscoll'], $models);
    }
}
