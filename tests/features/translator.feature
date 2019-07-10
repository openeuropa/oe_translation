@api
Feature: Translator role is available
  In order to be able to translate content
  As an admin
  I need to be able to see the Translator role

  Scenario: See the Translator role
    Given I am logged in as a user with the "administer permissions" permission
    When I am on "admin/people/roles"
    Then I should see the text "Translator"
