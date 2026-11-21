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
 * Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_reflectivejournal\local\journal;
use assignsubmission_reflectivejournal\local\presenter;
use assignsubmission_reflectivejournal\local\settings;
use assignsubmission_reflectivejournal\local\tutors;

defined('MOODLE_INTERNAL') || die();

/**
 * Reflective journal submission plugin.
 *
 * Each student builds up a Journal of Entries; the Assignment's own grading marks it once. See
 * CONTEXT.md for the vocabulary and docs/adr for the main decisions.
 */
class assign_submission_reflectivejournal extends assign_submission_plugin {
    /** @var string Plugin component. */
    private const COMPONENT = 'assignsubmission_reflectivejournal';

    /**
     * Name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', self::COMPONENT);
    }

    /**
     * The teacher's settings.
     *
     * @return settings
     */
    public function get_journal_settings(): settings {
        return settings::from_plugin($this);
    }

    /**
     * Settings shown on the Assignment form.
     *
     * @param MoodleQuickForm $mform The form.
     */
    public function get_settings(MoodleQuickForm $mform) {
        $settings = $this->get_journal_settings();
        $prefix = 'assignsubmission_reflectivejournal_';

        $mform->addElement('text', $prefix . 'maxentries', get_string('maxentries', self::COMPONENT), ['size' => 4]);
        $mform->setType($prefix . 'maxentries', PARAM_INT);
        $mform->setDefault($prefix . 'maxentries', $settings->maxentries);
        $mform->addHelpButton($prefix . 'maxentries', 'maxentries', self::COMPONENT);
        $mform->hideIf($prefix . 'maxentries', $prefix . 'enabled', 'notchecked');

        $boxes = [];
        foreach (settings::MODELS as $model) {
            $boxes[] = $mform->createElement('advcheckbox', $model, '', get_string('model_' . $model, self::COMPONENT));
        }
        $mform->addGroup($boxes, $prefix . 'models', get_string('allowedmodels', self::COMPONENT), ' ', true);
        foreach (settings::MODELS as $model) {
            $mform->setDefault($prefix . 'models[' . $model . ']', (int) $settings->allows_model($model));
        }
        $mform->addHelpButton($prefix . 'models', 'allowedmodels', self::COMPONENT);
        $mform->hideIf($prefix . 'models', $prefix . 'enabled', 'notchecked');

        $mform->addElement('select', $prefix . 'submissionmode', get_string('submissionmode', self::COMPONENT), [
            settings::MODE_DEADLINE => get_string('submissionmode_deadline', self::COMPONENT),
            settings::MODE_BUTTON => get_string('submissionmode_button', self::COMPONENT),
        ]);
        $mform->setDefault($prefix . 'submissionmode', $settings->submissionmode);
        $mform->addHelpButton($prefix . 'submissionmode', 'submissionmode', self::COMPONENT);
        $mform->hideIf($prefix . 'submissionmode', $prefix . 'enabled', 'notchecked');
    }

    /**
     * Save the teacher's settings. Form validation (lib.php) rejects bad values; anything that gets
     * here by another route is clamped to something valid.
     *
     * @param stdClass $data Form data.
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $prefix = 'assignsubmission_reflectivejournal_';
        $max = (int) ($data->{$prefix . 'maxentries'} ?? settings::DEFAULT_MAX_ENTRIES);
        $max = max(1, min(settings::MAX_ENTRIES_LIMIT, $max));

        $models = $data->{$prefix . 'models'} ?? null;
        if (is_array($models)) {
            $models = settings::clean_models(array_keys(array_filter($models)));
        } else if (is_string($models)) {
            $models = settings::clean_models(explode(',', $models));
        } else {
            $models = settings::MODELS;
        }
        if (!$models) {
            $models = settings::MODELS;
        }

        $mode = ($data->{$prefix . 'submissionmode'} ?? '') === settings::MODE_BUTTON
            ? settings::MODE_BUTTON : settings::MODE_DEADLINE;

        $this->set_config('maxentries', $max);
        $this->set_config('models', implode(',', $models));
        $this->set_config('submissionmode', $mode);
        return true;
    }

    /**
     * The student's editing form: their Locked Entries, then the Entry they are writing.
     *
     * @param mixed $submission The submission, or null if there is none yet.
     * @param MoodleQuickForm $mform The form.
     * @param stdClass $data Form data.
     * @param int $userid The student whose Journal this is.
     * @return bool
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        $settings = $this->get_journal_settings();
        $journal = $submission ? new journal($this->assignment, $submission) : null;
        $userid = (int) ($submission->userid ?? $userid);

        if ($journal) {
            $mform->addElement('html', presenter::journal($journal, $userid, false));
        }

        $open = $journal?->open_entry();
        if (!$open && $journal && !$journal->can_start_entry()) {
            $mform->addElement('static', 'reflectivejournal_full', '', get_string('maxentriesreached', self::COMPONENT));
            return true;
        }

        $number = $open ? $open->number : ($journal ? $journal->count_entries() + 1 : 1);
        $mform->addElement('header', 'reflectivejournal_header', get_string('entry', self::COMPONENT, $number));
        $mform->setExpanded('reflectivejournal_header');
        if ($open) {
            $mform->addElement('static', 'reflectivejournal_status', '',
                html_writer::div(get_string('entryeditable', self::COMPONENT, presenter::clock($open->editableuntil)),
                    'alert alert-info'));
        }

        $options = [];
        foreach ($settings->allowedmodels as $model) {
            $options[$model] = get_string('model_' . $model, self::COMPONENT);
        }
        $mform->addElement('select', 'reflectivejournal_model', get_string('model', self::COMPONENT), $options);
        $mform->setDefault('reflectivejournal_model', $open && $settings->allows_model($open->model)
            ? $open->model : $settings->allowedmodels[0]);

        foreach ($settings->allowedmodels as $model) {
            if ($model === 'none') {
                continue;
            }
            $name = 'reflectivejournal_prompt_' . $model;
            $mform->addElement('static', $name, get_string('promptguidance', self::COMPONENT),
                html_writer::div(get_string('prompt_' . $model, self::COMPONENT), 'rj-prompt border rounded p-2 bg-light',
                    ['data-model' => $model]));
            $mform->hideIf($name, 'reflectivejournal_model', 'neq', $model);
        }

        $mform->addElement('editor', 'reflectivejournal_editor', get_string('entrytext', self::COMPONENT), null,
            $this->editor_options());
        $mform->setType('reflectivejournal_editor', PARAM_RAW);
        if ($open) {
            $data->reflectivejournal_editor = ['text' => $open->text, 'format' => $open->format];
        }
        return true;
    }

    /**
     * Editor options: rich text only, no files.
     *
     * @return array
     */
    private function editor_options(): array {
        return [
            'maxfiles' => 0,
            'noclean' => false,
            'trusttext' => false,
            'context' => $this->assignment->get_context(),
            'enable_filemanagement' => false,
            'autosave' => false,
        ];
    }

    /**
     * Save the student's writing as a new Entry, or update the Entry in its Edit Window.
     *
     * @param stdClass $submission The submission.
     * @param stdClass $data Form data.
     * @return bool
     */
    public function save(stdClass $submission, stdClass $data) {
        if ($this->submission_is_empty($data)) {
            return true;
        }
        $editor = $data->reflectivejournal_editor;
        $settings = $this->get_journal_settings();
        $model = $data->reflectivejournal_model ?? $settings->allowedmodels[0];
        try {
            (new journal($this->assignment, $submission))->save_entry((string) $editor['text'],
                (int) ($editor['format'] ?? FORMAT_HTML), (string) $model);
        } catch (moodle_exception $e) {
            $this->set_error($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Whether the posted form has no Entry text.
     *
     * @param stdClass $data Form data.
     * @return bool
     */
    public function submission_is_empty(stdClass $data) {
        $text = $data->reflectivejournal_editor['text'] ?? '';
        return trim(html_to_text((string) $text, 0, false)) === '';
    }

    /**
     * Whether the Journal has no Entries.
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function is_empty(stdClass $submission) {
        return (new journal($this->assignment, $submission))->count_entries() === 0;
    }

    /**
     * Short summary for the submission status and grading table: "4 of 6 entries".
     *
     * @param stdClass $submission The submission.
     * @param bool $showviewlink Set to true to show a link to the full Journal.
     * @return string
     */
    public function view_summary(stdClass $submission, &$showviewlink) {
        global $USER;
        $journal = new journal($this->assignment, $submission);
        $count = count($journal->entries_visible_to((int) $USER->id));
        $showviewlink = $count > 0;
        $summary = get_string('entriescount', self::COMPONENT,
            (object) ['count' => $count, 'max' => $journal->settings()->maxentries]);
        return $summary . $this->journal_link($journal);
    }

    /**
     * The whole Journal, as the current user may see it. This is what the marking screen shows.
     *
     * @param stdClass $submission The submission.
     * @return string
     */
    public function view(stdClass $submission) {
        global $USER;
        $journal = new journal($this->assignment, $submission);
        return presenter::journal($journal, (int) $USER->id) . $this->journal_link($journal);
    }

    /**
     * Link from the submissions list to the student's Journal page, where Tutors write Comments.
     *
     * @param journal $journal The Journal.
     * @return string
     */
    private function journal_link(journal $journal): string {
        global $USER;
        if ($journal->is_submitted() || !tutors::can_view($this->assignment, $journal->get_studentid(), (int) $USER->id)) {
            return '';
        }
        $url = new moodle_url('/mod/assign/submission/reflectivejournal/journal.php',
            ['id' => $this->assignment->get_course_module()->id, 'userid' => $journal->get_studentid()]);
        return html_writer::div(html_writer::link($url, get_string('viewjournal', self::COMPONENT)), 'mt-1');
    }

    /**
     * In "deadline only" mode the student cannot submit early.
     *
     * @param stdClass $submission The submission.
     * @return bool|string True, or the reason submission is not allowed.
     */
    public function precheck_submission($submission) {
        if (!$this->get_journal_settings()->allows_early_submission()) {
            return get_string('submitatdeadline', self::COMPONENT);
        }
        return true;
    }

    /**
     * Journal Submission by the button: lock anything still in its Edit Window.
     *
     * @param stdClass $submission The submission.
     */
    public function submit_for_grading($submission) {
        (new journal($this->assignment, $submission))->lock_open_items();
    }

    /**
     * Carry Entries and Comments over to a new attempt.
     *
     * @param stdClass $oldsubmission Previous attempt.
     * @param stdClass $newsubmission New attempt.
     */
    public function add_attempt(stdClass $oldsubmission, stdClass $newsubmission) {
        $this->copy_submission($oldsubmission, $newsubmission);
    }

    /**
     * Copy Entries and Comments into another attempt, keeping their times and Locked state.
     *
     * @param stdClass $sourcesubmission Source attempt.
     * @param stdClass $destsubmission Destination attempt.
     * @return bool
     */
    public function copy_submission(stdClass $sourcesubmission, stdClass $destsubmission) {
        global $DB;
        if ($DB->record_exists(journal::ENTRY_TABLE, ['submission' => $destsubmission->id])) {
            return true;
        }
        $entries = $DB->get_records(journal::ENTRY_TABLE, ['submission' => $sourcesubmission->id], 'entrynumber ASC');
        foreach ($entries as $entry) {
            $comment = $DB->get_record(journal::COMMENT_TABLE, ['entry' => $entry->id]);
            $oldid = $entry->id;
            unset($entry->id);
            $entry->submission = $destsubmission->id;
            // The previous attempt is over, so everything carried over is Locked.
            $entry->timelocked = $entry->timelocked ?? $entry->timemodified + journal::EDIT_WINDOW;
            $newid = $DB->insert_record(journal::ENTRY_TABLE, $entry);
            if ($comment && $comment->entry == $oldid) {
                unset($comment->id);
                $comment->entry = $newid;
                $comment->timelocked = $comment->timelocked ?? $comment->timemodified + journal::EDIT_WINDOW;
                $DB->insert_record(journal::COMMENT_TABLE, $comment);
            }
        }
        return true;
    }

    /**
     * Remove a submission's Entries and Comments.
     *
     * @param stdClass $submission The submission.
     * @return bool
     */
    public function remove(stdClass $submission) {
        self::delete_entries('submission = ?', [$submission->id]);
        return true;
    }

    /**
     * Remove all Entries and Comments for the Assignment.
     *
     * @return bool
     */
    public function delete_instance() {
        self::delete_entries('assignment = ?', [$this->assignment->get_instance()->id]);
        return true;
    }

    /**
     * Delete Entries matching a condition, with their Comments.
     *
     * @param string $where SQL condition on the Entry table.
     * @param array $params Parameters.
     */
    public static function delete_entries(string $where, array $params): void {
        global $DB;
        $ids = $DB->get_fieldset_select(journal::ENTRY_TABLE, 'id', $where, $params);
        if ($ids) {
            [$insql, $inparams] = $DB->get_in_or_equal($ids);
            $DB->delete_records_select(journal::COMMENT_TABLE, "entry $insql", $inparams);
            $DB->delete_records_select(journal::ENTRY_TABLE, "id $insql", $inparams);
        }
    }

    /**
     * Settings exposed to web services and the mobile app.
     *
     * @return array
     */
    public function get_config_for_external() {
        $settings = $this->get_journal_settings();
        return [
            'maxentries' => $settings->maxentries,
            'models' => implode(',', $settings->allowedmodels),
            'submissionmode' => $settings->submissionmode,
        ];
    }
}
