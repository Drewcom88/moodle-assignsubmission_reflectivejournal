@assignsubmission @assignsubmission_reflectivejournal
Feature: Tutors comment on Locked Entries
  In order to guide a student's reflection as it develops
  As a tutor
  I need to write one Comment on each Locked Entry from the student's Journal page

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
      | activity | course | name    | assignsubmission_reflectivejournal_enabled | assignsubmission_onlinetext_enabled | assignsubmission_file_enabled | submissiondrafts | duedate     |
      | assign   | C1     | Journal | 1                                          | 0                                   | 0                             | 1                | ##+1 week## |
    And the following "assignsubmission_reflectivejournal > entries" exist:
      | assign  | user     | text                  | minutesago |
      | Journal | student1 | My first reflection   | 60         |

  Scenario: A tutor comments on an Entry and can edit it during the Edit Window
    Given I am on the "Journal" "assign activity" page logged in as teacher1
    And I navigate to "Submissions" in current page administration
    When I click on "View journal" "link" in the "Student 1" "table_row"
    Then I should see "My first reflection"
    And I set the field "Comment on this entry" to "A thoughtful start."
    And I press "Save changes"
    And I should see "Comment saved."
    And I should see "Saved. You can edit this comment until"

  Scenario: The student sees a Locked Comment but cannot reply
    Given the following "assignsubmission_reflectivejournal > entries" exist:
      | assign  | user     | text           | minutesago | comment         | commenter |
      | Journal | student1 | Second week    | 60         | Keep going!     | teacher1  |
    When I am on the "Journal" "assign activity" page logged in as student1
    Then I should see "Keep going!"
    And I should see "Comment from Teacher 1"
    And I should not see "Comment on this entry"
