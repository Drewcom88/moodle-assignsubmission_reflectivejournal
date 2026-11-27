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

namespace assignsubmission_reflectivejournal\privacy;

use assignsubmission_reflectivejournal\local\journal;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../journal_testcase.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\assignsubmission_reflectivejournal\privacy\provider::class)]
/**
 * Privacy tests for the Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignsubmission_reflectivejournal\privacy\provider
 */
final class provider_test extends \assignsubmission_reflectivejournal\journal_testcase {
    /** @var \assign The Assignment. */
    private \assign $assign;

    /** @var \stdClass The Tutor. */
    private \stdClass $tutor;

    protected function setUp(): void {
        parent::setUp();
        $this->tutor = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->assign = $this->create_journal_assign();
        $journal = $this->journal($this->assign);
        $entry = $journal->save_entry('<p>My reflection</p>', FORMAT_HTML, 'gibbs');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entry->id, (int) $this->tutor->id, '<p>Tutor note</p>', FORMAT_HTML);
        $this->wait(journal::EDIT_WINDOW);
    }

    public function test_metadata_describes_entries_comments_and_messages(): void {
        $items = provider::get_metadata(new \core_privacy\local\metadata\collection('assignsubmission_reflectivejournal'))
            ->get_collection();

        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertEqualsCanonicalizing([journal::ENTRY_TABLE, journal::COMMENT_TABLE, 'core_message'], $names);
    }

    public function test_a_students_export_includes_their_entries_and_the_comments_on_them(): void {
        $this->export_for($this->student);

        $data = writer::with_context($this->assign->get_context())->get_data($this->path());
        $this->assertStringContainsString('My reflection', $data->entries[0]['text']);
        $this->assertSame('gibbs', $data->entries[0]['model']);
        $this->assertStringContainsString('Tutor note', $data->entries[0]['comment']['text']);
    }

    public function test_a_tutors_export_includes_the_comments_they_wrote(): void {
        $contextlist = \mod_assign\privacy\provider::get_contexts_for_userid($this->tutor->id);
        $this->assertContains((int) $this->assign->get_context()->id, array_map('intval', $contextlist->get_contextids()));

        $this->export_for($this->tutor);

        $path = array_merge([get_string('privacy:studentpath', 'mod_assign'), $this->student->id], $this->path());
        $data = writer::with_context($this->assign->get_context())->get_data($path);
        $this->assertStringContainsString('Tutor note', $data->entries[0]['comment']['text']);
        $this->assertArrayNotHasKey('text', $data->entries[0], 'The student\'s own writing is not part of the tutor\'s data.');
    }

    public function test_tutors_who_commented_are_users_in_the_context(): void {
        $userlist = new userlist($this->assign->get_context(), 'mod_assign');

        \mod_assign\privacy\provider::get_users_in_context($userlist);

        $this->assertContains((int) $this->tutor->id, array_map('intval', $userlist->get_userids()));
    }

    public function test_deleting_a_students_data_removes_their_entries_and_the_comments_on_them(): void {
        global $DB;
        $context = $this->assign->get_context();

        \mod_assign\privacy\provider::delete_data_for_user(
            new approved_contextlist($this->student, 'mod_assign', [$context->id])
        );

        $this->assertSame(0, $DB->count_records(journal::ENTRY_TABLE));
        $this->assertSame(0, $DB->count_records(journal::COMMENT_TABLE));
    }

    public function test_deleting_several_students_removes_only_their_entries(): void {
        global $DB;
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->journal($this->assign, $other)->save_entry('Other student', FORMAT_HTML, 'none');

        \mod_assign\privacy\provider::delete_data_for_users(
            new approved_userlist($this->assign->get_context(), 'mod_assign', [$this->student->id])
        );

        $this->assertSame(
            ['Other student'],
            array_values($DB->get_fieldset_select(journal::ENTRY_TABLE, 'entrytext', '1=1'))
        );
    }

    public function test_deleting_everything_in_the_context_removes_all_entries(): void {
        global $DB;

        \mod_assign\privacy\provider::delete_data_for_all_users_in_context($this->assign->get_context());

        $this->assertSame(0, $DB->count_records(journal::ENTRY_TABLE));
    }

    /**
     * Run a mod_assign export for one user.
     *
     * @param \stdClass $user The user.
     */
    private function export_for(\stdClass $user): void {
        $this->setUser($user);
        \mod_assign\privacy\provider::export_user_data(
            new approved_contextlist($user, 'mod_assign', [$this->assign->get_context()->id])
        );
    }

    /**
     * Export path for attempt 1.
     *
     * @return array
     */
    private function path(): array {
        return [get_string('privacy:attemptpath', 'mod_assign', 1),
            get_string('privacy:path', 'assignsubmission_reflectivejournal')];
    }
}
