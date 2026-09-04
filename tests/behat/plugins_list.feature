@aiprovider @aiprovider_datacurso
Feature: Datacurso plugins list tab
  In order to know which ecosystem plugins are installed
  As an administrator with the view-reports permission
  I need the plugins list to show each plugin, its install status and a link

  Background:
    Given I log in as "admin"
    And I visit "/ai/provider/datacurso/admin/report_sections.php"
    And I follow "Datacurso plugins list"

  Scenario: The eight ecosystem plugins are listed with status and link
    Then I should see "Course Creator AI"
    And I should see "Ranking Activities AI"
    And I should see "Forum AI"
    And I should see "Assign AI"
    And I should see "Tutor AI"
    And I should see "Share Certificate AI"
    And I should see "Student Life Story AI"
    And I should see "SmartRules AI"
    And I should see "8" plugins listed
    And each listed plugin should show an installation status of "Yes" or "No"
    And each listed plugin should expose a link
