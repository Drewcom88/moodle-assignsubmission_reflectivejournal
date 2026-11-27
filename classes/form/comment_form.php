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

namespace assignsubmission_reflectivejournal\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The Tutor's Comment form for one Entry.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment_form extends \moodleform {
    /**
     * The editor's field name for an Entry. Each Entry's form uses its own name, so that several
     * editors on one page each get their own element id.
     *
     * @param int $entryid The Entry.
     * @return string
     */
    public static function field(int $entryid): string {
        return 'comment_editor_' . $entryid;
    }

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $entryid = (int) $this->_customdata['entryid'];
        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid', $this->_customdata['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'entryid', $entryid);
        $mform->setType('entryid', PARAM_INT);
        $mform->addElement(
            'editor',
            self::field($entryid),
            get_string('addcomment', 'assignsubmission_reflectivejournal'),
            null,
            [
                'maxfiles' => 0,
                'context' => $this->_customdata['context'],
                'enable_filemanagement' => false,
                'autosave' => false,
            ]
        );
        $mform->setType(self::field($entryid), PARAM_RAW);
        $mform->addRule(self::field($entryid), null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('savechanges'));
    }
}
