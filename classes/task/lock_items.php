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

namespace assignsubmission_reflectivejournal\task;

use assignsubmission_reflectivejournal\local\locker;
use assignsubmission_reflectivejournal\local\journal;

/**
 * Scheduled task: lock Entries and Comments whose Edit Window has closed.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Drewcom88
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lock_items extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_lockitems', 'assignsubmission_reflectivejournal');
    }

    /**
     * Run the task.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $now = journal::now();
        locker::process($now);
    }
}
