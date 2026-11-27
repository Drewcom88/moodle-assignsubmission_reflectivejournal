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
 * Library callbacks for the Reflective journal submission plugin.
 *
 * @package    assignsubmission_reflectivejournal
 * @copyright  2026 Obline Education
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use assignsubmission_reflectivejournal\local\settings;

/**
 * Validate the Assignment settings form when the Reflective journal is enabled.
 *
 * Moodle 4.5 has no settings-validation hook for Assignment subplugins, so this uses the course
 * module form's plugin validation callback, which works on 4.5 and 5.3 alike.
 *
 * @param moodleform_mod $form The form.
 * @param array $data Submitted data.
 * @return array Errors keyed by element name.
 */
function assignsubmission_reflectivejournal_coursemodule_validation($form, $data) {
    $prefix = 'assignsubmission_reflectivejournal_';
    if (($data['modulename'] ?? '') !== 'assign' || empty($data[$prefix . 'enabled'])) {
        return [];
    }
    return assignsubmission_reflectivejournal_validate_settings($data);
}

/**
 * The rules a Reflective journal Assignment's settings must follow.
 *
 * @param array $data Assignment form data.
 * @return array Errors keyed by element name.
 */
function assignsubmission_reflectivejournal_validate_settings(array $data): array {
    $prefix = 'assignsubmission_reflectivejournal_';
    $component = 'assignsubmission_reflectivejournal';
    $errors = [];
    if (empty($data['duedate'])) {
        $errors['duedate'] = get_string('errorrequiresduedate', $component);
    }
    if (!empty($data['teamsubmission'])) {
        $errors['teamsubmission'] = get_string('errorteamsubmission', $component);
    }
    if (empty($data['submissiondrafts'])) {
        $errors['submissiondrafts'] = get_string('errorrequiresdrafts', $component);
    }
    $max = $data[$prefix . 'maxentries'] ?? settings::DEFAULT_MAX_ENTRIES;
    if (!is_numeric($max) || (int) $max < 1 || (int) $max > settings::MAX_ENTRIES_LIMIT) {
        $errors[$prefix . 'maxentries'] = get_string('errormaxentries', $component, settings::MAX_ENTRIES_LIMIT);
    }
    $models = $data[$prefix . 'models'] ?? null;
    if (is_array($models) && !settings::clean_models(array_keys(array_filter($models)))) {
        $errors[$prefix . 'models'] = get_string('errornomodels', $component);
    }
    return $errors;
}
