@aiprovider @aiprovider_datacurso @MDL-E2E-001
Feature: Configure the licence and per-service credit limits of a tenant
  In order to control AI credit consumption per plugin in my tenant
  As a site administrator
  I need to enable, set and persist per-service rate limits from the tenant configuration page

  Background:
    Given I log in as "admin"
    And I visit "/ai/provider/datacurso/admin/settings_tenant.php"

  Scenario: Enable a service limit, set its values and persist them
    When I set the field "ratelimit_local_coursegen_enable" to "1"
    And I set the field "ratelimit_local_coursegen_limit" to "2000"
    And I set the field "ratelimit_local_coursegen_window_value" to "2"
    And I set the field "ratelimit_local_coursegen_window_unit" to "hours"
    And I set the field "credit[local_coursegen][course_image]" to "1800"
    And I press "Save changes"
    Then I should see "Changes saved"
    And the field "ratelimit_local_coursegen_limit" matches value "2000"
    And the field "ratelimit_local_coursegen_window_value" matches value "2"
    And the field "credit[local_coursegen][course_image]" matches value "1800"

  @javascript
  Scenario: Disabling a service hides its dependent fields
    Given I expand all fieldsets
    And I set the field "ratelimit_local_coursegen_enable" to "1"
    And "ratelimit_local_coursegen_limit" "field" should be visible
    When I set the field "ratelimit_local_coursegen_enable" to "0"
    Then "ratelimit_local_coursegen_limit" "field" should not be visible
    And "ratelimit_local_coursegen_window_value" "field" should not be visible
    And "credit[local_coursegen][course_image]" "field" should not be visible
