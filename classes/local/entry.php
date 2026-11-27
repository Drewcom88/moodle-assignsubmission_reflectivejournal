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

/**
 * One saved piece of reflective writing in a Journal.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry {
    /**
     * Constructor.
     *
     * @param int $id Record id.
     * @param int $number Position in the Journal, starting at 1.
     * @param int $authorid The student who wrote it.
     * @param string $model Reflective Model chosen for it.
     * @param string $text Entry text.
     * @param int $format Text format.
     * @param int $timemodified Time of the last save.
     * @param bool $locked Whether the Edit Window has closed.
     * @param int $editableuntil End of the Edit Window.
     * @param comment|null $comment The Comment, if one exists and the viewer may see it.
     */
    public function __construct(
        /** @var int Record id. */
        public readonly int $id,
        /** @var int Position in the Journal, starting at 1. */
        public readonly int $number,
        /** @var int The student who wrote it. */
        public readonly int $authorid,
        /** @var string Reflective Model chosen for it. */
        public readonly string $model,
        /** @var string Entry text. */
        public readonly string $text,
        /** @var int Text format. */
        public readonly int $format,
        /** @var int Time of the last save. */
        public readonly int $timemodified,
        /** @var bool Whether the Edit Window has closed. */
        public readonly bool $locked,
        /** @var int End of the Edit Window. */
        public readonly int $editableuntil,
        /** @var comment|null The Comment, if one exists and the viewer may see it. */
        public readonly ?comment $comment = null,
    ) {
    }
}
