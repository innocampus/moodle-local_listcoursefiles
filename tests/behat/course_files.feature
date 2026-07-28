@local @local_listcoursefiles @javascript @_file_upload
Feature: List course files
  In order to review and manage the files used in a course
  As a teacher
  I need the course files page to list uploaded files and let me filter, download and relicense them

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And I am on the "C1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I add a "File" to section "1" using the activity chooser
    And I set the following fields to these values:
      | Name | Course test file |
    And I upload "local/listcoursefiles/tests/fixtures/document.txt" file to "Select files" filemanager
    And I upload "local/listcoursefiles/tests/fixtures/image.png" file to "Select files" filemanager
    And I upload "local/listcoursefiles/tests/fixtures/other.json" file to "Select files" filemanager
    And I press "Save and return to course"

  Scenario: The course files page lists an uploaded file with its details
    When I navigate to "Course files" in current page administration
    Then I should see "List course files"
    And I should see "document.txt"
    And I should see "File" in the "document.txt" "table_row"
    And I should see "Document" in the "document.txt" "table_row"
    And I should see "Teacher One" in the "document.txt" "table_row"

  Scenario: A teacher sees the download and change-license actions
    When I navigate to "Course files" in current page administration
    Then I should see "Download selected files"
    And I should see "Change license to"

  Scenario: Filtering by file type keeps matching files and hides the rest
    Given I navigate to "Course files" in current page administration
    When I set the field "filetype" to "Document"
    Then I should see "document.txt"
    And I should not see "image.png"
    And I should not see "other.json"

  Scenario: Filtering by the image file type shows only images
    Given I navigate to "Course files" in current page administration
    When I set the field "filetype" to "Image"
    Then I should see "image.png"
    And I should not see "document.txt"

  Scenario: Filtering by the other file type shows unrecognised MIME types
    Given I navigate to "Course files" in current page administration
    When I set the field "filetype" to "Other"
    Then I should see "other.json"
    And I should not see "document.txt"
    And I should not see "image.png"

  Scenario: Filtering by a file type with no matches shows the empty state
    Given I navigate to "Course files" in current page administration
    When I set the field "filetype" to "Audio"
    Then I should see "No files found"

  Scenario: Filtering by component and file type together
    Given I navigate to "Course files" in current page administration
    When I set the field "filetype" to "Document"
    And I set the field "component" to "File"
    Then I should see "document.txt"
    And I should not see "image.png"

  Scenario: Assignment submissions are hidden by default but shown under all files
    Given the following "activities" exist:
      | activity | course | name     | assignsubmission_file_enabled | assignsubmission_file_maxfiles | assignsubmission_file_maxsizebytes |
      | assign   | C1     | Assign 1 | 1                             | 1                              | 102400                             |
    And the following "mod_assign > submissions" exist:
      | assign   | user     | file                                                |
      | Assign 1 | student1 | local/listcoursefiles/tests/fixtures/submission.txt |
    And I navigate to "Course files" in current page administration
    Then I should see "document.txt"
    And I should not see "submission.txt"
    When I set the field "component" to "All files"
    Then I should see "submission.txt"

  Scenario: A teacher changes the license of a file
    Given I navigate to "Course files" in current page administration
    And I click on ".local_listcoursefiles_filecheckbox" "css_element" in the "document.txt" "table_row"
    And I set the field "license" to "Public domain"
    When I press "Change license to"
    Then I should see "Public domain" in the "document.txt" "table_row"

  Scenario: A teacher downloads a single selected file as a zip
    Given I navigate to "Course files" in current page administration
    When I click on ".local_listcoursefiles_filecheckbox" "css_element" in the "document.txt" "table_row"
    Then the selected course files should download as a zip containing:
      | document.txt |

  Scenario: A teacher downloads multiple selected files as a zip
    Given I navigate to "Course files" in current page administration
    When I click on ".local_listcoursefiles_filecheckbox" "css_element" in the "document.txt" "table_row"
    And I click on ".local_listcoursefiles_filecheckbox" "css_element" in the "image.png" "table_row"
    Then the selected course files should download as a zip containing:
      | document.txt |
      | image.png    |

  Scenario: A student without the view capability has no course files link
    When I am on the "C1" "Course" page logged in as "student1"
    Then I should not see "Course files"
