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
 * Behat data generator for the Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_assignsubmission_reflectivejournal_generator extends behat_generator_base {
    /**
     * Entities this generator can create.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'entries' => [
                'singular' => 'entry',
                'datagenerator' => 'entry',
                'required' => ['assign', 'user'],
                'switchids' => ['assign' => 'cmid', 'user' => 'userid', 'commenter' => 'commenterid'],
            ],
        ];
    }

    /**
     * Find an Assignment's cmid by name.
     *
     * @param string $name Activity name or idnumber.
     * @return int
     */
    protected function get_assign_id(string $name): int {
        return $this->get_cm_by_activity_name('assign', $name)->id;
    }

    /**
     * Find a commenter's user id by username.
     *
     * @param string $username Username.
     * @return int
     */
    protected function get_commenter_id(string $username): int {
        return $this->get_user_id($username);
    }
}
