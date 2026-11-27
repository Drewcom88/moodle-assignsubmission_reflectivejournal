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
 * English strings for the Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcomment'] = 'Comment on this entry';
$string['allowedmodels'] = 'Allowed reflective models';
$string['allowedmodels_help'] = 'The reflective models students can choose from for each entry. The guidance for the chosen model is shown while the student writes.';
$string['backtoassignment'] = 'Back to the assignment';
$string['backtooverview'] = 'Back to all journals';
$string['colawaiting'] = 'Waiting for a comment';
$string['colentries'] = 'Entries';
$string['colstatus'] = 'Status';
$string['comment'] = 'Tutor comment';
$string['commentby'] = 'Comment from {$a->name}, {$a->date}';
$string['commenteditable'] = 'Saved. You can edit this comment until {$a}. After that, it will be shared with the student and locked.';
$string['commentinprogress'] = 'A comment on this entry is being written by {$a}.';
$string['commentsaved'] = 'Comment saved.';
$string['commentsclosed'] = 'This journal has been submitted, so no more comments can be added.';
$string['default'] = 'Enabled by default';
$string['default_help'] = 'If set, this submission method will be enabled by default for all new assignments.';
$string['enabled'] = 'Reflective journal';
$string['enabled_help'] = 'If enabled, students write a series of reflective entries. Their tutor can comment on each entry, and the whole journal is marked once.';
$string['entriescount'] = '{$a->count} of {$a->max} entries';
$string['entry'] = 'Entry {$a}';
$string['entryeditable'] = 'Saved. You can edit this entry until {$a}. After that, it will be shared with your tutor and locked.';
$string['entrysaved'] = 'Saved {$a}';
$string['entrytext'] = 'Your entry';
$string['errorcommentnotallowed'] = 'You cannot comment on this entry.';
$string['errorentrynotallowed'] = 'No more entries can be added to this journal.';
$string['errormaxentries'] = 'The maximum number of entries must be between 1 and {$a}.';
$string['errormodel'] = 'Choose one of the allowed reflective models.';
$string['errornomodels'] = 'Allow at least one reflective model.';
$string['errorrequiresdrafts'] = 'A Reflective journal needs "Require students to click the submit button" set to Yes, so that saving an entry does not submit the whole journal.';
$string['errorrequiresduedate'] = 'A Reflective journal needs a due date, because the journal is submitted automatically at the deadline.';
$string['errorteamsubmission'] = 'A Reflective journal cannot be used with group submissions.';
$string['journalfor'] = 'Reflective journal: {$a}';
$string['maxentries'] = 'Maximum number of entries';
$string['maxentries_help'] = 'The most entries a student can add to their journal. There is no minimum.';
$string['maxentriesreached'] = 'You have written the maximum number of entries for this journal.';
$string['messageprovider:commentlocked'] = 'Your tutor has commented on a reflective journal entry';
$string['messageprovider:entrylocked'] = 'A student has added a reflective journal entry';
$string['model'] = 'Reflective model';
$string['model_driscoll'] = 'Driscoll';
$string['model_gibbs'] = 'Gibbs';
$string['model_kolb'] = 'Kolb';
$string['model_none'] = 'None';
$string['nocomment'] = 'No comment';
$string['noentries'] = 'No entries yet.';
$string['nostudents'] = 'There are no students to show.';
$string['notifycommentlocked_body'] = 'Your tutor has commented on Entry {$a->number} of your reflective journal in "{$a->assignment}".

Read the comment: {$a->url}';
$string['notifycommentlocked_small'] = 'Your tutor commented on Entry {$a->number} in {$a->assignment}';
$string['notifycommentlocked_subject'] = 'New comment on your reflective journal: {$a->assignment}';
$string['notifyentrylocked_body'] = '{$a->student} has added Entry {$a->number} to their reflective journal in "{$a->assignment}".

View the journal: {$a->url}';
$string['notifyentrylocked_small'] = '{$a->student} added Entry {$a->number} in {$a->assignment}';
$string['notifyentrylocked_subject'] = 'New reflective journal entry: {$a->assignment}';
$string['pluginname'] = 'Reflective journal';
$string['privacy:metadata:comment'] = 'Tutor comments on reflective journal entries.';
$string['privacy:metadata:comment:commenttext'] = 'The text of the comment.';
$string['privacy:metadata:comment:timemodified'] = 'The time the comment was last saved.';
$string['privacy:metadata:comment:userid'] = 'The tutor who wrote the comment.';
$string['privacy:metadata:entry'] = 'Entries in a student\'s reflective journal.';
$string['privacy:metadata:entry:entrytext'] = 'The text of the entry.';
$string['privacy:metadata:entry:model'] = 'The reflective model chosen for the entry.';
$string['privacy:metadata:entry:timemodified'] = 'The time the entry was last saved.';
$string['privacy:metadata:entry:userid'] = 'The student who wrote the entry.';
$string['privacy:metadata:messages'] = 'Notifications sent when entries and comments are shared.';
$string['privacy:path'] = 'Reflective journal';
$string['prompt_driscoll'] = '<p><strong>What?</strong> Describe what happened. What did you see, do and hear? What was your role?</p>
<p><strong>So what?</strong> What did it mean to you? What did you learn, and why does it matter?</p>
<p><strong>Now what?</strong> What will you do differently next time? What do you need to learn or practise?</p>';
$string['prompt_gibbs'] = '<p><strong>Description:</strong> What happened? Who was involved, and what was the context?</p>
<p><strong>Feelings:</strong> What were you thinking and feeling at the time?</p>
<p><strong>Evaluation:</strong> What was good and bad about the experience?</p>
<p><strong>Analysis:</strong> What sense can you make of the situation? What helped or got in the way?</p>
<p><strong>Conclusion:</strong> What else could you have done? What have you learned?</p>
<p><strong>Action plan:</strong> If it happened again, what would you do? What will you do next?</p>';
$string['prompt_kolb'] = '<p><strong>Concrete experience:</strong> What did you do or experience?</p>
<p><strong>Reflective observation:</strong> Looking back, what did you notice? How did it go?</p>
<p><strong>Abstract conceptualisation:</strong> What do you now understand? How does it connect to theory or ideas you know?</p>
<p><strong>Active experimentation:</strong> How will you apply what you have learned? What will you try next time?</p>';
$string['promptguidance'] = 'Reflective prompt';
$string['statusinprogress'] = 'In progress';
$string['statussubmitted'] = 'Submitted';
$string['student'] = 'Student';
$string['submissionmode'] = 'Submission';
$string['submissionmode_button'] = 'At the deadline, or earlier when the student clicks "Submit assignment"';
$string['submissionmode_deadline'] = 'Automatically at the deadline only';
$string['submissionmode_help'] = 'The journal is always submitted automatically at the latest of the due date, cut-off date and the student\'s extension. Choose whether students may also submit the whole journal early.';
$string['submitatdeadline'] = 'This journal is submitted automatically at the deadline, so it cannot be submitted early.';
$string['task_lockitems'] = 'Lock reflective journal entries and comments';
$string['task_submitjournals'] = 'Submit reflective journals at the deadline';
$string['viewjournal'] = 'View journal';
$string['viewjournals'] = 'Reflective journals';
