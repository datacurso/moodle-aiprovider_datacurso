@aiprovider @aiprovider_datacurso @MDL-E2E-003 @javascript
Feature: General consumption report tab
  In order to understand yearly AI credit usage at a glance
  As an administrator with the view-reports permission
  I need the general report with a year selector, credit cards and the four charts

  Background:
    Given I log in as "admin"
    And the following "aiprovider_datacurso > consumption" records exist:
      | userid | service         | action            | credits | balance | timecreated |
      | admin  | local_coursegen | /course/execute   | 2000    | 8000    | ##2026-01-15## |
      | admin  | aiprovider_datacurso | /provider/images/generations | 30 | 7970 | ##2026-02-10## |
    And I navigate to the Datacurso AI provider reports page
    And I follow "General report"

  Scenario: Year selector and credit cards are shown
    When I set the field "year" to "2026"
    Then I should see "Available credits"
    And I should see "Consumed credits"
    And I should see "2030" in the "Consumed credits" "region"

  Scenario: The four consumption charts are rendered
    When I set the field "year" to "2026"
    Then "Consumption by month" "text" should exist
    And "Consumption by action" "text" should exist
    And "Consumption by day" "text" should exist
    And "Consumption by service" "text" should exist
