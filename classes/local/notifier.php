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

namespace assignsubmission_reflectivejournal\local;

use core\message\message;
use core_user;
use moodle_url;
use stdClass;

/**
 * Sends the plugin's Moodle notifications.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notifier {
    /**
     * Tell the student's Tutor that an Entry has been Locked.
     *
     * @param \assign $assign The Assignment.
     * @param stdClass $entry Entry record.
     */
    public static function entry_locked(\assign $assign, stdClass $entry): void {
        $student = core_user::get_user($entry->userid);
        if (!$student) {
            return;
        }
        $url = new moodle_url(
            '/mod/assign/submission/reflectivejournal/journal.php',
            ['id' => $assign->get_course_module()->id, 'userid' => $entry->userid]
        );
        $studentname = $assign->is_blind_marking()
            ? get_string('hiddenuser', 'assign') . $assign->get_uniqueid_for_user($entry->userid)
            : fullname($student);
        foreach (tutors::for_student($assign, (int) $entry->userid) as $tutorid) {
            $tutor = core_user::get_user($tutorid);
            if (!$tutor) {
                continue;
            }
            $a = (object) [
                'student' => $studentname,
                'number' => $entry->entrynumber,
                'assignment' => format_string(
                    $assign->get_instance()->name,
                    true,
                    ['context' => $assign->get_context()]
                ),
                'url' => $url->out(false),
            ];
            self::send('entrylocked', $tutor, $assign, $url, $a, 'notifyentrylocked');
        }
    }

    /**
     * Tell the student that a Comment on their Entry has been Locked.
     *
     * @param \assign $assign The Assignment.
     * @param stdClass $entry Entry record.
     * @param stdClass $comment Comment record.
     */
    public static function comment_locked(\assign $assign, stdClass $entry, stdClass $comment): void {
        $student = core_user::get_user($entry->userid);
        if (!$student) {
            return;
        }
        $url = new moodle_url('/mod/assign/view.php', ['id' => $assign->get_course_module()->id]);
        $a = (object) [
            'number' => $entry->entrynumber,
            'assignment' => format_string($assign->get_instance()->name, true, ['context' => $assign->get_context()]),
            'url' => $url->out(false),
        ];
        self::send('commentlocked', $student, $assign, $url, $a, 'notifycommentlocked');
    }

    /**
     * Send one notification.
     *
     * @param string $name Message provider name.
     * @param stdClass $to Recipient.
     * @param \assign $assign The Assignment.
     * @param moodle_url $url Link for the notification.
     * @param stdClass $a Placeholders for the strings.
     * @param string $stringprefix Prefix of the _subject, _body and _small strings.
     */
    private static function send(
        string $name,
        stdClass $to,
        \assign $assign,
        moodle_url $url,
        stdClass $a,
        string $stringprefix
    ): void {
        $component = 'assignsubmission_reflectivejournal';
        $message = new message();
        $message->component = $component;
        $message->name = $name;
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $to;
        $message->subject = get_string($stringprefix . '_subject', $component, $a);
        $message->fullmessage = get_string($stringprefix . '_body', $component, $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = get_string($stringprefix . '_small', $component, $a);
        $message->notification = 1;
        $message->contexturl = $url->out(false);
        $message->contexturlname = $a->assignment;
        $message->courseid = $assign->get_course()->id;
        message_send($message);
    }
}
