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
use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;
use mod_assign\privacy\assign_plugin_request_data;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/submission/reflectivejournal/locallib.php');

/**
 * Privacy provider for the Reflective journal submission plugin.
 *
 * Students' Entries (and the Comments on them) are exported and deleted with their submissions.
 * Tutors' Comments are exported as part of the tutor's own data. As in core Assignment, deleting a
 * tutor's data does not remove the Comments they wrote for students.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \mod_assign\privacy\assignsubmission_provider,
    \mod_assign\privacy\assignsubmission_user_provider {
    /**
     * Describe the personal data stored.
     *
     * @param collection $collection The collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(journal::ENTRY_TABLE, [
            'userid' => 'privacy:metadata:entry:userid',
            'entrytext' => 'privacy:metadata:entry:entrytext',
            'model' => 'privacy:metadata:entry:model',
            'timemodified' => 'privacy:metadata:entry:timemodified',
        ], 'privacy:metadata:entry');
        $collection->add_database_table(journal::COMMENT_TABLE, [
            'userid' => 'privacy:metadata:comment:userid',
            'commenttext' => 'privacy:metadata:comment:commenttext',
            'timemodified' => 'privacy:metadata:comment:timemodified',
        ], 'privacy:metadata:comment');
        $collection->link_subsystem('core_message', 'privacy:metadata:messages');
        return $collection;
    }

    /**
     * Add the Assignments where a user wrote Comments. Students are found by mod_assign.
     *
     * @param int $userid The user.
     * @param contextlist $contextlist The context list.
     */
    public static function get_context_for_userid_within_submission(int $userid, contextlist $contextlist) {
        $sql = "SELECT ctx.id
                  FROM {" . journal::COMMENT_TABLE . "} c
                  JOIN {" . journal::ENTRY_TABLE . "} e ON e.id = c.entry
                  JOIN {course_modules} cm ON cm.instance = e.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE c.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
    }

    /**
     * Add the students a tutor has commented on, so their Comments are exported for the tutor.
     *
     * @param \mod_assign\privacy\useridlist $useridlist The list.
     */
    public static function get_student_user_ids(\mod_assign\privacy\useridlist $useridlist) {
        $sql = "SELECT DISTINCT e.userid AS id
                  FROM {" . journal::COMMENT_TABLE . "} c
                  JOIN {" . journal::ENTRY_TABLE . "} e ON e.id = c.entry
                 WHERE c.userid = :teacherid AND e.assignment = :assignid";
        $useridlist->add_from_sql($sql, ['teacherid' => $useridlist->get_teacherid(),
            'assignid' => $useridlist->get_assignid()]);
    }

    /**
     * Add the tutors who wrote Comments in this Assignment.
     *
     * @param \core_privacy\local\request\userlist $userlist The list.
     */
    public static function get_userids_from_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT c.userid
                  FROM {" . journal::COMMENT_TABLE . "} c
                  JOIN {" . journal::ENTRY_TABLE . "} e ON e.id = c.entry
                  JOIN {course_modules} cm ON cm.instance = e.assignment
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Export a submission's Entries and Comments. For the student this is their whole Journal; for a
     * tutor exporting their own data, it is the Comments on that student's Journal.
     *
     * @param assign_plugin_request_data $exportdata The request.
     */
    public static function export_submission_user_data(assign_plugin_request_data $exportdata) {
        global $DB;
        $submission = $exportdata->get_pluginobject();
        $context = $exportdata->get_context();
        $forteacher = $exportdata->get_user() !== null;
        $entries = $DB->get_records(journal::ENTRY_TABLE, ['submission' => $submission->id], 'entrynumber ASC');
        $data = [];
        foreach ($entries as $entry) {
            $comment = $DB->get_record(journal::COMMENT_TABLE, ['entry' => $entry->id]);
            if ($forteacher && !$comment) {
                continue;
            }
            $item = ['entry' => (int) $entry->entrynumber];
            if (!$forteacher) {
                $item += [
                    'model' => $entry->model,
                    'text' => format_text($entry->entrytext, $entry->entryformat, ['context' => $context]),
                    'timemodified' => transform::datetime($entry->timemodified),
                ];
            }
            if ($comment) {
                $item['comment'] = [
                    'text' => format_text($comment->commenttext, $comment->commentformat, ['context' => $context]),
                    'timemodified' => transform::datetime($comment->timemodified),
                ];
            }
            $data[] = $item;
        }
        if ($data) {
            $path = array_merge(
                $exportdata->get_subcontext(),
                [get_string('privacy:path', 'assignsubmission_reflectivejournal')]
            );
            writer::with_context($context)->export_data($path, (object) ['entries' => $data]);
        }
    }

    /**
     * Delete all Entries and Comments in an Assignment.
     *
     * @param assign_plugin_request_data $requestdata The request.
     */
    public static function delete_submission_for_context(assign_plugin_request_data $requestdata) {
        \assign_submission_reflectivejournal::delete_entries('assignment = ?', [$requestdata->get_assignid()]);
    }

    /**
     * Delete one student's submission's Entries and the Comments on them.
     *
     * @param assign_plugin_request_data $deletedata The request.
     */
    public static function delete_submission_for_userid(assign_plugin_request_data $deletedata) {
        \assign_submission_reflectivejournal::delete_entries('submission = ?', [$deletedata->get_pluginobject()->id]);
    }

    /**
     * Delete the Entries and Comments for several submissions.
     *
     * @param assign_plugin_request_data $deletedata The request.
     */
    public static function delete_submissions(assign_plugin_request_data $deletedata) {
        global $DB;
        if (empty($deletedata->get_submissionids())) {
            return;
        }
        [$sql, $params] = $DB->get_in_or_equal($deletedata->get_submissionids());
        \assign_submission_reflectivejournal::delete_entries("submission $sql", $params);
    }
}
