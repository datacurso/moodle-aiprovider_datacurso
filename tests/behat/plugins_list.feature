@aiprovider @aiprovider_datacurso
Feature: Datacurso plugins list tab
  In order to know which suite plugins are installed and switch them on or off
  As an administrator
  I need the plugins list to show each plugin, its install status, an "Enabled" switch and a link to its settings

  Background:
    Given I log in as "admin"
    And I visit "/ai/provider/datacurso/admin/report_sections.php?tab=pluginslist"

  Scenario: The published suite plugins are listed with their switch and moodle.org link
    Then I should see "Course Creator AI"
    And I should see "Ranking Activities AI"
    And I should see "Forum AI"
    And I should see "Assign AI"
    And I should see "Tutor AI"
    And I should see "Share Certificate AI"
    And I should see "Student Life Story AI"
    And I should see "SmartRules AI"
    And I should see "Enabled"
    And "Disable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"
    And "Settings" "link" should not exist in the "Forum AI" "table_row"

  # Jedomi is not published yet and is only listed where it is installed, so this scenario and the
  # settings page scenarios below need local_jedomi in the codebase. Its absence is covered by PHPUnit.
  Scenario: Jedomi is listed with its settings and moodle.org link when it is installed
    Then I should see "Jedomi"
    And "Settings" "link" should exist in the "Jedomi" "table_row"
    And "Jedomi link" "link" should exist in the "Jedomi" "table_row"
    And I should not see "https://moodle.org/plugins/local_jedomi"

  @javascript
  Scenario: Disable and enable a suite plugin from its switch without leaving the tab
    When I toggle the "Disable Forum AI" admin switch "off"
    Then I should see "Forum AI disabled."
    And I reload the page
    And "Enable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"
    And I toggle the "Enable Forum AI" admin switch "on"
    And I should see "Forum AI enabled."
    And I reload the page
    And "Disable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"

  Scenario: Disable and enable a suite plugin from its settings page
    When I click on "Settings" "link" in the "Jedomi" "table_row"
    Then I should see "Jedomi settings"
    And I set the field "Enabled" to "0"
    And I press "Save changes"
    And I should see "The settings of Jedomi have been saved."
    And "Enable Jedomi" "checkbox" should exist in the "Jedomi" "table_row"
    And I click on "Settings" "link" in the "Jedomi" "table_row"
    And I set the field "Enabled" to "1"
    And I press "Save changes"
    And "Disable Jedomi" "checkbox" should exist in the "Jedomi" "table_row"

  Scenario: Cancelling the settings page returns to the plugins list without changes
    When I click on "Settings" "link" in the "Jedomi" "table_row"
    And I set the field "Enabled" to "0"
    And I press "Cancel"
    Then "Disable Jedomi" "checkbox" should exist in the "Jedomi" "table_row"
