@aiprovider @aiprovider_datacurso @MDL-E2E-002
Feature: Credit consumption history tab
  In order to audit AI credit consumption
  As an administrator with the view-reports permission
  I need to browse, filter, sort, paginate and download the consumption history

  Background:
    Given I log in as "admin"
    And the following "aiprovider_datacurso > consumption" records exist:
      | userid | service         | action            | credits | balance | timecreated |
      | admin  | local_coursegen | /course/execute   | 2000    | 8000    | ##yesterday## |
      | admin  | local_forum_ai  | /forum/chat       | 3       | 7997    | ##today##     |
    And I navigate to the Datacurso AI provider reports page
    And I follow "Consumption history"

  Scenario: The history table lists synced consumptions
    Then I should see "Course creation" in the "reportbuilder-table" "table"
    And I should see "2000" in the "reportbuilder-table" "table"

  @javascript
  Scenario: Filter, sort and paginate the history
    When I click on "Filters" "button"
    And I set the field "Service" to "AI Course Creator"
    And I click on "Apply" "button"
    Then I should see "Course creation" in the "reportbuilder-table" "table"
    And I should not see "/forum/chat" in the "reportbuilder-table" "table"
    When I click on "Date" "link" in the "reportbuilder-table" "table"
    Then the rows of the "reportbuilder-table" table should be sorted by date

  Scenario: Download the history as CSV
    When I set the field "Download table data as" to "Comma separated values (.csv)"
    And I press "Download"
    Then the downloaded file should contain "credits"
