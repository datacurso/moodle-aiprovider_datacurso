@aiprovider @aiprovider_datacurso @MDL-E2E-004
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
    And "Forum AI link" "link" should exist in the "Forum AI" "table_row"
    And "Settings" "link" should not exist in the "Forum AI" "table_row"
    And I should not see "https://moodle.org/plugins/local_forum_ai"

  Scenario: Jedomi is not listed while it is not installed
    Then I should not see "Jedomi"

  @javascript
  Scenario: Disable and enable a suite plugin from its switch without leaving the tab
    Given the following config values are set as admin:
      | version | 2026010100 | local_forum_ai |
    And I reload the page
    When I toggle the "Disable Forum AI" admin switch "off"
    Then I should see "Forum AI disabled."
    And I reload the page
    And "Enable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"
    And I toggle the "Enable Forum AI" admin switch "on"
    And I should see "Forum AI enabled."
    And I reload the page
    And "Disable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"

  Scenario: Disable and enable a suite plugin from its settings page
    Given the following config values are set as admin:
      | version | 2026010100 | local_forum_ai |
    When I visit "/ai/provider/datacurso/admin/plugin_settings.php?component=local_forum_ai"
    Then I should see "Forum AI settings"
    And I set the field "Enabled" to "0"
    And I press "Save changes"
    And I should see "The settings of Forum AI have been saved."
    And "Enable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"
    And I visit "/ai/provider/datacurso/admin/plugin_settings.php?component=local_forum_ai"
    And I set the field "Enabled" to "1"
    And I press "Save changes"
    And "Disable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"

  Scenario: Cancelling the settings page returns to the plugins list without changes
    Given the following config values are set as admin:
      | version | 2026010100 | local_forum_ai |
    When I visit "/ai/provider/datacurso/admin/plugin_settings.php?component=local_forum_ai"
    And I set the field "Enabled" to "0"
    And I press "Cancel"
    Then "Disable Forum AI" "checkbox" should exist in the "Forum AI" "table_row"
