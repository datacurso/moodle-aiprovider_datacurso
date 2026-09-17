@aiprovider @aiprovider_datacurso @MDL-E2E-004
Feature: Datacurso plugins list tab
  In order to know which ecosystem plugins are installed
  As an administrator with the view-reports permission
  I need the plugins list to show each plugin, its install status and a link

  Background:
    Given I log in as "admin"
    And I navigate to the Datacurso AI provider reports page
    And I follow "Datacurso plugins"

  Scenario: The eight ecosystem plugins are listed with status and link
    Then I should see "AI Course Creator"
    And I should see "Datacurso Ratings"
    And I should see "Forum AI"
    And I should see "Assignment AI"
    And I should see "DT Tutor"
    And I should see "Social Certificate"
    And I should see "Life Story"
    And I should see "Smart Rules"
    And I should see "8" plugins listed
    And each listed plugin should show an installation status of "Installed" or "Not installed"
    And each listed plugin should expose a link
