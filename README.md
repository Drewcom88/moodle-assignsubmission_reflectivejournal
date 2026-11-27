# Reflective journal (Assignment submission plugin)

**Short description:** A Reflective journal submission type for Moodle Assignments: students write a series of
reflective entries, their tutor comments on each one, and the whole journal is marked once.

## Description

`assignsubmission_reflectivejournal` adds a **Reflective journal** submission type to Moodle Assignments.

- **Entries.** Students write one entry at a time, in their preferred editor. Entries are rich text only;
  files can't be attached. After saving, a student can keep editing an entry until 15 minutes after their
  last save (the Edit Window). The entry is then locked, shared with their tutor, and the next empty entry
  appears. The teacher sets the maximum number of entries (default 6, up to 52). There is no minimum.
- **Reflective models.** For each entry the student chooses Gibbs, Driscoll, Kolb or None, from the models the
  teacher allows. The chosen model's guidance is shown while they write. The wording is in language strings,
  so a site administrator can change it through Moodle's language customisation.
- **Tutor comments.** On the assignment page, staff click **Reflective journals**. This shows their students,
  how many entries each has written, and how many are waiting for a comment.
  - Opening a student's journal shows each entry with a comment box directly underneath.
  - The tutor writes one comment per locked entry.
  - A comment has its own 15-minute Edit Window. The student is then notified and sees the comment under their
    entry, but can't reply.
- **Who the tutor is.** A student's tutor is their allocated marker, which needs marking workflow and marker
  allocation turned on.
  - With several markers (Moodle 5.3), it is the first one.
  - With no marker allocated, anyone who can grade acts as the tutor.
  - Editing teachers and managers can always cover.
  - Separate groups are respected throughout.
- **Journal submission.** The journal is submitted automatically at the latest of the due date, the cut-off
  date and the student's extension. If the teacher allows it, the student can also submit early with the
  assignment's **Submit assignment** button. Anything still in an Edit Window is locked and kept.
- **Marking.** The grader shows every entry's number, the time it was last saved, its text and its comment.
  Click **View full** to expand them. The tutor then gives one grade and feedback, as for any assignment.
- **Reopening.** Reverting to draft, granting a new extension or allowing a new attempt reopens the journal.
  Entries and comments carry over and stay locked.

## Requirements

- Moodle 4.5 LTS or 5.3 LTS.
- Works with PostgreSQL and MariaDB/MySQL.
- No other plugins, Composer packages or external services are needed.

## Installation

1. Install like any Moodle plugin. Either upload the ZIP from the
   [releases page](https://github.com/Drewcom88/moodle-reflective-journal/releases) under
   **Site administration → Plugins → Install plugins**, or put the plugin's files in
   `mod/assign/submission/reflectivejournal` and visit **Site administration → Notifications**.
2. Make sure Moodle's cron runs every minute. Two scheduled tasks lock entries and comments when their Edit
   Window ends (and send the notifications), and submit journals at the deadline.

No other post-installation steps are needed.

## Setting up an assignment

1. Under **Submission types**, tick **Reflective journal**.
2. Set the maximum number of entries, the allowed reflective models and how the journal is submitted.

The assignment form also requires:
- a due date;
- no group submission;
- **Require students to click the submit button** set to **Yes**, so that saving an entry never submits the
  whole journal.

## Privacy

The plugin stores:

- **Each entry:** the student, the text, the reflective model chosen, and when it was created, saved and locked.
- **Each comment:** the tutor, the text, and when it was created, saved and locked.

It sends Moodle notifications when an entry or comment is shared. It uses no external services.

The Privacy API is implemented. Students' entries (with the comments on them) and tutors' own comments are
exported and deleted through Moodle's data privacy tools. As in core Assignment, deleting a tutor's data does
not remove the comments they wrote for students.

## Known limitations

- **The "Submit assignment" button still appears in "deadline only" mode.** Moodle doesn't let submission
  plugins hide it. If a student clicks it, they are told the journal is submitted automatically, and the
  plugin refuses early submission by any route, including the Moodle app.
- **Automatic submission at the deadline doesn't send Moodle's own notifications.** It fires the standard
  "submitted" event and updates activity completion, but not the grader notification or the student's
  submission receipt.

## Support

- Report bugs and request features in the
  [issue tracker](https://github.com/Drewcom88/moodle-reflective-journal/issues).
- Source code is on [GitHub](https://github.com/Drewcom88/moodle-reflective-journal).

## Development

- Vocabulary: [`CONTEXT.md`](CONTEXT.md). Decisions: [`docs/adr`](docs/adr).
- Tests: PHPUnit (`tests/`) and Behat (`tests/behat/`). GitHub Actions runs moodle-plugin-ci on Moodle 4.5 and
  5.3 with PostgreSQL and MariaDB.
- Releases: push a tag such as `v1.0.0`. The release workflow then attaches an installable ZIP to the release.

## Licence

GNU GPL v3 or later. See [`COPYING.txt`](COPYING.txt).
