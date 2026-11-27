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

#[\PHPUnit\Framework\Attributes\CoversClass(\assign_submission_reflectivejournal::class)]
/**
 * Tests for the hooks Moodle Assignment calls on the plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assign_submission_reflectivejournal
 */
final class plugin_test extends journal_testcase {
    public function test_new_assignments_get_the_default_settings(): void {
        $assign = $this->create_journal_assign();

        $settings = $assign->get_submission_plugin_by_type('reflectivejournal')->get_journal_settings();

        $this->assertSame(6, $settings->maxentries);
        $this->assertSame(['gibbs', 'driscoll', 'kolb', 'none'], $settings->allowedmodels);
        $this->assertSame('deadline', $settings->submissionmode);
    }

    /**
     * Settings that the Assignment form must refuse.
     *
     * @return array
     */
    public static function invalid_settings_provider(): array {
        return [
            'no due date' => [['duedate' => 0], 'duedate'],
            'group submission' => [['teamsubmission' => 1], 'teamsubmission'],
            'saving would submit' => [['submissiondrafts' => 0], 'submissiondrafts'],
            'too many entries' => [['assignsubmission_reflectivejournal_maxentries' => 53],
                'assignsubmission_reflectivejournal_maxentries'],
            'no entries' => [['assignsubmission_reflectivejournal_maxentries' => 0],
                'assignsubmission_reflectivejournal_maxentries'],
            'no models' => [['assignsubmission_reflectivejournal_models' => ['gibbs' => 0, 'driscoll' => 0,
                'kolb' => 0, 'none' => 0]], 'assignsubmission_reflectivejournal_models'],
        ];
    }

    /**
     * The Assignment form refuses each invalid setting.
     *
     * @dataProvider invalid_settings_provider
     * @param array $override Form data that breaks a rule.
     * @param string $field The field that should get the error.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid_settings_provider')]
    public function test_the_assignment_form_refuses_invalid_settings(array $override, string $field): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/submission/reflectivejournal/lib.php');
        $data = $override + $this->valid_form_data();

        $errors = assignsubmission_reflectivejournal_coursemodule_validation(null, $data);

        $this->assertArrayHasKey($field, $errors);
    }

    public function test_the_assignment_form_accepts_valid_settings(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/submission/reflectivejournal/lib.php');

        $this->assertSame([], assignsubmission_reflectivejournal_coursemodule_validation(null, $this->valid_form_data()));
        $this->assertSame(
            [],
            assignsubmission_reflectivejournal_coursemodule_validation(
                null,
                ['assignsubmission_reflectivejournal_enabled' => 0, 'duedate' => 0] + $this->valid_form_data()
            ),
            'Nothing is checked when the Reflective journal is off.'
        );
    }

    public function test_a_student_saving_the_assignment_form_creates_an_entry(): void {
        $assign = $this->create_journal_assign();
        $this->setUser($this->student);

        $saved = $assign->save_submission($this->form_data('<p>Week 1</p>', 'driscoll'), $notices);

        $this->assertTrue($saved, implode(', ', $notices ?? []));
        $entries = $this->journal($assign)->entries_visible_to((int) $this->student->id);
        $this->assertSame('<p>Week 1</p>', $entries[0]->text);
        $this->assertSame('driscoll', $entries[0]->model);
        $this->assertSame(
            ASSIGN_SUBMISSION_STATUS_DRAFT,
            $this->journal($assign)->get_submission()->status,
            'Saving an entry never submits the whole journal.'
        );
    }

    public function test_the_edit_form_offers_the_open_entry_with_its_deadline(): void {
        $assign = $this->create_journal_assign();
        $this->setUser($this->student);
        $assign->save_submission($this->form_data('<p>Draft</p>', 'gibbs'), $notices);

        $html = $this->render_edit_form($assign);

        $this->assertStringContainsString(get_string(
            'entryeditable',
            'assignsubmission_reflectivejournal',
            userdate(self::START + journal::EDIT_WINDOW, '%H:%M')
        ), $html);
        $this->assertStringContainsString('Draft', $html);
        $this->assertStringContainsString('Entry 1', $html);
    }

    public function test_the_edit_form_shows_only_the_allowed_models_and_their_prompts(): void {
        $assign = $this->create_journal_assign([
            'assignsubmission_reflectivejournal_models' => ['gibbs' => 0, 'driscoll' => 1, 'kolb' => 0, 'none' => 1],
        ]);
        $this->setUser($this->student);

        $html = $this->render_edit_form($assign);

        $this->assertStringContainsString('<option value="driscoll"', $html);
        $this->assertStringContainsString('<option value="none"', $html);
        $this->assertStringNotContainsString('<option value="gibbs"', $html);
        $this->assertStringContainsString('So what?', $html, 'Driscoll guidance is on the page.');
        $this->assertStringNotContainsString('Action plan', $html, 'Gibbs guidance is not.');
    }

    public function test_the_edit_form_says_when_the_maximum_is_reached(): void {
        $assign = $this->create_journal_assign(['assignsubmission_reflectivejournal_maxentries' => 1]);
        $this->setUser($this->student);
        $assign->save_submission($this->form_data('<p>Only entry</p>', 'none'), $notices);
        $this->wait(journal::EDIT_WINDOW);

        $html = $this->render_edit_form($assign);

        $this->assertStringContainsString(get_string('maxentriesreached', 'assignsubmission_reflectivejournal'), $html);
        $this->assertStringNotContainsString('reflectivejournal_editor', $html);
    }

    public function test_the_summary_counts_the_entries_the_viewer_can_see(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $journal->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_entry('Week 2', FORMAT_HTML, 'none');
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');

        $this->setUser($this->teacher);
        $summary = $plugin->view_summary($journal->get_submission(), $showlink);

        $this->assertStringContainsString('1 of 6 entries', $summary);
        $this->assertTrue($showlink);
    }

    public function test_the_marking_view_shows_each_entry_with_its_comment(): void {
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $first = $journal->save_entry('<p>Week one text</p>', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($first->id, (int) $this->teacher->id, '<p>Tutor thoughts</p>', FORMAT_HTML);
        $journal->save_entry('<p>Week two text</p>', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');

        $this->setUser($this->teacher);
        $html = $plugin->view($journal->get_submission());

        $this->assertMatchesRegularExpression(
            '/Entry 1.*Week one text.*Tutor thoughts.*Entry 2.*Week two text.*No comment/s',
            $html
        );
        $this->assertStringContainsString(userdate(self::START, get_string('strftimedatetime', 'langconfig')), $html);
    }

    public function test_removing_a_submission_deletes_its_entries_and_comments(): void {
        global $DB;
        $assign = $this->create_journal_assign();
        $journal = $this->journal($assign);
        $entry = $journal->save_entry('Week 1', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $journal->save_comment($entry->id, (int) $this->teacher->id, 'Comment', FORMAT_HTML);

        $assign->get_submission_plugin_by_type('reflectivejournal')->remove($journal->get_submission());

        $this->assertSame(0, $journal->count_entries());
        $this->assertFalse($DB->record_exists(journal::COMMENT_TABLE, ['entry' => $entry->id]));
    }

    public function test_the_grading_table_shows_each_students_journal_summary(): void {
        global $PAGE;
        $assign = $this->create_journal_assign();
        $this->journal($assign)->save_entry('<p>Week 1</p>', FORMAT_HTML, 'none');
        $this->wait(journal::EDIT_WINDOW);
        $this->setUser($this->teacher);
        $PAGE->set_url(new \moodle_url(
            '/mod/assign/view.php',
            ['id' => $assign->get_course_module()->id, 'action' => 'grading']
        ));
        $PAGE->set_cm($assign->get_course_module(), $this->course);

        $html = $assign->view('grading');

        $this->assertStringContainsString('1 of 6 entries', $html);
        $this->assertStringContainsString(get_string('viewjournal', 'assignsubmission_reflectivejournal'), $html);
    }

    public function test_staff_get_a_link_to_the_journal_overview_on_the_assignment_page(): void {
        $assign = $this->create_journal_assign();
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');

        $this->setUser($this->teacher);
        $html = $plugin->view_header();

        $this->assertStringContainsString(get_string('viewjournals', 'assignsubmission_reflectivejournal'), $html);
        $this->assertStringContainsString('/mod/assign/submission/reflectivejournal/journals.php?id=' .
            $assign->get_course_module()->id, $html);
    }

    public function test_students_do_not_get_the_journal_overview_link(): void {
        $assign = $this->create_journal_assign();
        $plugin = $assign->get_submission_plugin_by_type('reflectivejournal');

        $this->setUser($this->student);

        $this->assertSame('', $plugin->view_header());
    }

    /**
     * Form data for an Assignment with the Reflective journal turned on and valid settings.
     *
     * @return array
     */
    private function valid_form_data(): array {
        return [
            'modulename' => 'assign',
            'assignsubmission_reflectivejournal_enabled' => 1,
            'assignsubmission_reflectivejournal_maxentries' => 6,
            'assignsubmission_reflectivejournal_models' => ['gibbs' => 1, 'driscoll' => 1, 'kolb' => 1, 'none' => 1],
            'duedate' => self::START + WEEKSECS,
            'teamsubmission' => 0,
            'submissiondrafts' => 1,
        ];
    }

    /**
     * What the student's edit form posts.
     *
     * @param string $text Entry text.
     * @param string $model Reflective Model.
     * @return \stdClass
     */
    private function form_data(string $text, string $model): \stdClass {
        return (object) [
            'userid' => $this->student->id,
            'reflectivejournal_model' => $model,
            'reflectivejournal_editor' => ['text' => $text, 'format' => FORMAT_HTML],
        ];
    }

    /**
     * Render the plugin's part of the student's edit form.
     *
     * @param \assign $assign The Assignment.
     * @return string
     */
    private function render_edit_form(\assign $assign): string {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');
        $submission = $assign->get_user_submission($this->student->id, false) ?: null;
        $form = new \MoodleQuickForm('test', 'post', '');
        $data = new \stdClass();
        $assign->get_submission_plugin_by_type('reflectivejournal')
            ->get_form_elements_for_user($submission, $form, $data, $this->student->id);
        if (isset($data->reflectivejournal_editor)) {
            $form->setDefault('reflectivejournal_editor', $data->reflectivejournal_editor);
        }
        return $form->toHtml();
    }
}
