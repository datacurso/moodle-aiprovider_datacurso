@aiprovider @aiprovider_datacurso
Feature: Configure per-service credit limits
  In order to control AI credit consumption per plugin
  As an administrator with the view-reports permission
  I need to enable, set and persist per-service rate limits from the provider instance form

  Background:
    Given the following "core_ai > ai providers" exist:
      | provider             | name                  | enabled | licensekey |
      | aiprovider_datacurso | Datacurso AI provider | 1       | testkey123 |
    And I log in as "admin"
    And I navigate to "AI > AI providers" in site administration
    And I click on the "Settings" link in the table row containing "Datacurso AI provider"

  Scenario: Enable a service limit, set its values and persist them
    When I set the field "ratelimit_local_coursegen_enable" to "1"
    And I set the field "ratelimit_local_coursegen_limit" to "2000"
    And I set the field "ratelimit_local_coursegen_window_value" to "2"
    And I set the field "ratelimit_local_coursegen_window_unit" to "hours"
    And I set the field "ratelimit_local_coursegen_credit_course_image" to "1800"
    And I press "Save changes"
    Then I should see "AI provider instance updated."
    When I navigate to "AI > AI providers" in site administration
    And I click on the "Settings" link in the table row containing "Datacurso AI provider"
    Then the field "ratelimit_local_coursegen_limit" matches value "2000"
    And the field "ratelimit_local_coursegen_window_value" matches value "2"
    And the field "ratelimit_local_coursegen_credit_course_image" matches value "1800"

  @javascript
  Scenario: Disabling a service hides its dependent fields
    Given I set the field "ratelimit_local_coursegen_enable" to "1"
    Then I should see "Credit limit per window"
    When I set the field "ratelimit_local_coursegen_enable" to "0"
    Then "ratelimit_local_coursegen_limit" "field" should not be visible
    And "ratelimit_local_coursegen_window" "group" should not be visible
    And "ratelimit_local_coursegen_credit_course_image" "field" should not be visible
