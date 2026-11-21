@assignsubmission @assignsubmission_reflectivejournal
Feature: Students write Entries in a Reflective journal
  In order to reflect on my learning week by week
  As a student
  I need to save Entries, edit them for 15 minutes and see the guidance for my chosen model

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Student   | 1        |
      | teacher1 | Teacher   | 1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | course | name    | assignsubmission_reflectivejournal_enabled | assignsubmission_onlinetext_enabled | assignsubmission_file_enabled | submissiondrafts | duedate              |
      | assign   | C1     | Journal | 1                                          | 0                                   | 0                             | 1                | ##+1 week##          |

  @javascript
  Scenario: A student saves their first Entry and can still edit it
    Given I am on the "Journal" "assign activity" page logged in as student1
    When I press "Add submission"
    And I set the field "Reflective model" to "Driscoll"
    Then I should see "So what?"
    And I should not see "Action plan"
    And I set the field "Your entry" to "This week I learned to give feedback."
    And I press "Save changes"
    And I should see "1 of 6 entries"
    And I press "Edit submission"
    And I should see "Saved. You can edit this entry until"
    And the field "Your entry" matches value "This week I learned to give feedback."

  Scenario: A student cannot start a new Entry while the last one is in its Edit Window
    Given the following "assignsubmission_reflectivejournal > entries" exist:
      | assign  | user     | text                | minutesago |
      | Journal | student1 | Locked first entry  | 60         |
      | Journal | student1 | Fresh second entry  | 0          |
    When I am on the "Journal" "assign activity" page logged in as student1
    And I press "Edit submission"
    Then I should see "Locked first entry"
    And I should see "Entry 2"
    And I should see "Saved. You can edit this entry until"
    And I should not see "Entry 3"

  Scenario: Staff only see Locked Entries
    Given the following "assignsubmission_reflectivejournal > entries" exist:
      | assign  | user     | text               | minutesago |
      | Journal | student1 | Shared with tutor  | 60         |
      | Journal | student1 | Still being edited | 0          |
    When I am on the "Journal" "assign activity" page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    Then I should see "1 of 6 entries" in the "Student 1" "table_row"
