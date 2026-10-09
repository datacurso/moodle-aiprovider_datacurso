@aiprovider @aiprovider_datacurso @MDL-E2E-005
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
    When I navigate to the Datacurso AI provider reports page
    Then I should see "Consumption history"
    And I should see "General report"

  Scenario: A user with only the view-reports permission does not get the configuration tab
    Given I log in as "manager"
    When I navigate to the Datacurso AI provider reports page
    Then I should see "Consumption history"
    And "Configuration" "link" should not exist

  Scenario: A user with only the view-reports permission cannot open the configuration form directly
    Given I log in as "manager"
    When I visit "/ai/provider/datacurso/admin/report_sections.php?tab=config"
    Then I should see "Sorry, but you do not currently have permissions to do that"
    And I should not see "Save changes"

  Scenario: A user with the manage-configuration permission can open and save the configuration form
    Given the following "permission overrides" exist:
      | capability                         | permission | role    | contextlevel | reference |
      | aiprovider/datacurso:manageconfig  | Allow      | manager | System       |           |
    And I log in as "manager"
    When I navigate to the Datacurso AI provider reports page
    And I follow "Configuration"
    And I press "Save changes"
    Then I should see "Configuration saved"

  Scenario: A user without the permission is denied the reports page
    Given I log in as "teacher"
    When I try to open the Datacurso AI provider reports page directly
    Then I should see "Sorry, but you do not currently have permissions to do that"
    And I should not see the "Datacurso AI reports" navigation link
