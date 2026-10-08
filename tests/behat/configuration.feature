@aiprovider @aiprovider_datacurso @MDL-E2E-001
Feature: Configure per-service credit limits
  In order to control AI credit consumption per plugin
  As an administrator with the view-reports permission
  I need to enable, set and persist per-service rate limits from the Configuration tab

  Background:
    Given I log in as "admin"
    And I navigate to the Datacurso AI provider reports page
    And I follow "Configuration"

  Scenario: Enable a service limit, set its values and persist them
    When I set the field "enable[local_coursegen]" to "1"
    And I set the field "limit[local_coursegen]" to "2000"
    And I set the field "windowvalue[local_coursegen]" to "2"
    And I set the field "windowunit[local_coursegen]" to "hours"
    And I set the field "credit[local_coursegen][course_image]" to "1800"
    And I press "Save changes"
    Then I should see "Changes saved"
    When I follow "Configuration"
    Then the field "limit[local_coursegen]" matches value "2000"
    And the field "windowvalue[local_coursegen]" matches value "2"
    And the field "credit[local_coursegen][course_image]" matches value "1800"

  @javascript
  Scenario: Disabling a service hides its dependent fields
    Given I set the field "enable[local_coursegen]" to "1"
    Then I should see "Credit limit" in the "head_local_coursegen" "fieldset"
    When I set the field "enable[local_coursegen]" to "0"
    Then "limit[local_coursegen]" "field" should not be visible
    And "windowgroup_local_coursegen" "group" should not be visible
    And "credit[local_coursegen][course_image]" "field" should not be visible
