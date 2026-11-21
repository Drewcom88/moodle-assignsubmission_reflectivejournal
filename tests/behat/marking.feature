@assignsubmission @assignsubmission_reflectivejournal
Feature: Tutors mark the submitted Journal once
  In order to give one Grade for the whole Journal
  As a tutor
  I need to see every Entry and its Comment on the marking screen

  @javascript
  Scenario: The marking screen shows each Entry with its Comment
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
      | assign  | user     | text           | minutesago | comment      | commenter |
      | Journal | student1 | Week one text  | 120        | Lovely work  | teacher1  |
    And the following "assignsubmission_reflectivejournal > entries" exist:
      | assign  | user     | text           | minutesago | submitted |
      | Journal | student1 | Week two text  | 60         | 1         |
    When I am on the "Journal" "assign activity" page logged in as teacher1
    And I go to "Student 1" "Journal" activity advanced grading page
    Then I should see "Entry 1"
    And I should see "Week one text"
    And I should see "Lovely work"
    And I should see "Entry 2"
    And I should see "Week two text"
    And I should see "No comment"
