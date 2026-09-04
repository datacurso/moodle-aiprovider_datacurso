@aiprovider @aiprovider_datacurso
Feature: Restricted access to the reports page
  In order to protect AI usage data
  As the platform
  I must expose the reports navigation link and page only to users with the view-reports permission

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | manager  | Meg       | Manager  | manager@example.com  |
      | teacher  | Tom       | Teacher  | teacher@example.com  |
    And the following "system role assigns" exist:
      | user    | role    | contextlevel |
      | manager | manager | System       |

  Scenario: A user with the permission can reach the reports page
    Given I log in as "manager"
    When I visit "/ai/provider/datacurso/admin/report_sections.php"
    Then I should see "Credits consumption history"
    And I should see "General report"

  Scenario: A user without the permission is denied the reports page
    Given I log in as "teacher"
    When I visit "/ai/provider/datacurso/admin/report_sections.php"
    Then I should see "Sorry, but you do not currently have permissions to do that"
    And "Datacurso AI Provider" "link" should not exist
