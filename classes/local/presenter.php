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

use html_writer;

/**
 * Builds the HTML for Journals.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class presenter {
    /** @var string Plugin component. */
    private const COMPONENT = 'assignsubmission_reflectivejournal';

    /**
     * The whole Journal as a viewer sees it: each Entry's number, time last saved, text and Comment.
     *
     * @param journal $journal The Journal.
     * @param int $viewerid The viewer.
     * @param bool $includeopen Include an Entry that is still in its Edit Window.
     * @param callable|null $aftereach Called with each entry; may return extra HTML to show under it.
     * @return string
     */
    public static function journal(
        journal $journal,
        int $viewerid,
        bool $includeopen = true,
        ?callable $aftereach = null
    ): string {
        $context = $journal->get_assign()->get_context();
        $html = '';
        foreach ($journal->entries_visible_to($viewerid) as $entry) {
            if (!$includeopen && !$entry->locked) {
                continue;
            }
            $html .= self::entry($entry, $context, $aftereach ? (string) $aftereach($entry) : '');
        }
        if ($html === '') {
            $html = html_writer::div(get_string('noentries', self::COMPONENT), 'text-muted');
        }
        return html_writer::div($html, 'assignsubmission_reflectivejournal');
    }

    /**
     * One Entry with its Comment.
     *
     * @param entry $entry The Entry.
     * @param \context $context Context for formatting.
     * @param string $extra Extra HTML under the Entry.
     * @return string
     */
    public static function entry(entry $entry, \context $context, string $extra = ''): string {
        $html = html_writer::tag('h4', get_string('entry', self::COMPONENT, $entry->number), ['class' => 'h5']);
        $html .= html_writer::div(
            get_string('entrysaved', self::COMPONENT, self::time($entry->timemodified)),
            'text-muted small mb-2'
        );
        if (!$entry->locked) {
            $html .= html_writer::div(
                get_string('entryeditable', self::COMPONENT, self::clock($entry->editableuntil)),
                'alert alert-info'
            );
        }
        $html .= html_writer::div(format_text($entry->text, $entry->format, ['context' => $context]), 'rj-entry-text');
        if ($entry->locked) {
            $html .= self::comment($entry->comment, $context);
        }
        $html .= $extra;
        return html_writer::div($html, 'rj-entry border rounded p-3 mb-3', ['data-entry' => $entry->number]);
    }

    /**
     * A Comment block, or "No comment".
     *
     * @param comment|null $comment The Comment.
     * @param \context $context Context for formatting.
     * @return string
     */
    public static function comment(?comment $comment, \context $context): string {
        if (!$comment) {
            return html_writer::div(get_string('nocomment', self::COMPONENT), 'rj-comment text-muted small mt-2');
        }
        $author = $comment->authorid ? \core_user::get_user($comment->authorid) : null;
        $a = (object) [
            'name' => s($author ? fullname($author) : get_string('unknownuser')),
            'date' => self::time($comment->timemodified),
        ];
        $html = html_writer::div(get_string('commentby', self::COMPONENT, $a), 'small fw-bold');
        if (!$comment->locked) {
            $html .= html_writer::div(
                get_string('commenteditable', self::COMPONENT, self::clock($comment->editableuntil)),
                'alert alert-info'
            );
        }
        $html .= format_text($comment->text, $comment->format, ['context' => $context]);
        return html_writer::div($html, 'rj-comment bg-light border-start border-3 p-2 mt-2');
    }

    /**
     * Date and time.
     *
     * @param int $time Timestamp.
     * @return string
     */
    public static function time(int $time): string {
        return userdate($time, get_string('strftimedatetime', 'langconfig'));
    }

    /**
     * Time of day only, for "until HH:MM".
     *
     * @param int $time Timestamp.
     * @return string
     */
    public static function clock(int $time): string {
        return userdate($time, get_string('strftimetime24', 'langconfig'));
    }
}
