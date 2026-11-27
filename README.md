# Reflective journal (Assignment submission plugin)

`assignsubmission_reflectivejournal` adds a **Reflective journal** submission type to Moodle Assignments.
Students build up a series of reflective Entries, their tutor comments on each one as it arrives, and the
whole Journal is marked once through the Assignment's normal grading.

Supports Moodle 4.5 LTS and 5.3 LTS.

## How it works

- **Entries.** The student writes one Entry at a time in their preferred editor (rich text only, no files).
  After saving, they can keep editing it until 15 minutes after their last save (the Edit Window). It is then
  Locked, shared with their Tutor, and the next empty Entry appears. There is a teacher-set maximum
  (default 6, up to 52) and no minimum.
- **Reflective models.** For each Entry the student chooses Gibbs, Driscoll, Kolb or None (the teacher picks
  which are allowed). The model's guidance is shown while they write. The wording is in language strings,
  so a site admin can change it through Moodle's language customisation.
- **Tutor Comments.** The Tutor is the student's allocated marker (marking workflow plus marker allocation).
  With several markers on Moodle 5.3 it is the first one. With no marker allocated, anyone who can grade acts
  as the Tutor. Editing teachers and managers can always cover. On the Assignment page, staff click
  **Reflective journals** to see their students, how many Entries each has written and how many are waiting for
  a Comment. Opening a student's journal shows each Entry with a comment box directly underneath (the
  Submissions table also links to it). The Tutor writes one Comment per Locked Entry. The Comment has its own
  15-minute Edit Window, then the student is notified and sees it under their Entry, without being able to reply.
- **Journal Submission.** The Journal is submitted automatically at the latest of the due date, the cut-off
  date and the student's extension. If the teacher allows it, the student can also submit early with the
  Assignment's "Submit assignment" button. Anything still in an Edit Window is locked and kept.
- **Marking.** The marking screen shows every Entry's number, time last saved, text and Comment. The tutor
  then gives one Grade and Feedback as for any Assignment.
- **Reopening.** Reverting to draft, a new extension or a new attempt reopens the Journal. Entries and
  Comments carry over and stay Locked.

## Setting up an Assignment

The Assignment needs a due date, must not use group submission, and must have "Require students to click
the submit button" set to Yes (so that saving an Entry never submits the whole Journal). The form enforces
all three.

## Development

- Vocabulary: [`CONTEXT.md`](CONTEXT.md). Decisions: [`docs/adr`](docs/adr).
- Scheduled tasks run every minute: one locks items whose Edit Window has closed and sends notifications,
  the other submits Journals whose deadline has passed.
- Tests: PHPUnit (`tests/`) and Behat (`tests/behat/`). GitHub Actions runs both on Moodle 4.5 and 5.3 with
  moodle-plugin-ci.

## Licence

GNU GPL v3 or later.
