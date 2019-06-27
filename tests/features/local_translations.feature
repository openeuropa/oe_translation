@api
Feature: Local translations
  In order to easily have multilingual content
  As a translator
  I need to be able to translate content

  Scenario: Translate content.
    Given a translatable node with the "My title" title and "My body" body
    And I am logged in as a user with the "translator" role
    When I visit "the content administration page"
    And I click "My title"
    Then I should see the link "Translate"

    # Start translating the node.
    When I click "Translate"
    Then I should see "n/a" in the "Bulgarian" row
    And I should see "Not translated" in the "Bulgarian" row
    And I should see "n/a" in the "Czech" row
    And I should see "Not translated" in the "Czech" row

    When I click "Translate locally" in the "Bulgarian" row
    Then the translation form element for the "Title" field should contain "My title"
    And the translation form element for the "Body" field should contain "My body"

    When I fill in the translation form element for the "Title" field with "Bulgarian title"
    And I fill in the translation form element for the "Body" field with "Bulgarian body"
    And I press "Save and come back later"
    Then I should see "Translations of My title"
    And I should see "Not translated" in the "Bulgarian" row

    # Finalize translation.
    When I click "Edit local translation" in the "Bulgarian" row
    Then the translation form element for the "Title" field should contain "Bulgarian title"
    And the translation form element for the "Body" field should contain "Bulgarian body"

    When I press "Save and finish translation"
    Then I should see "Translations of My title"
    And I should not see "Not translated" in the "Bulgarian" row
    And I should see "Published" in the "Bulgarian" row
    When I click "Bulgarian title" in the "Bulgarian" row
    Then I should see "Bulgarian title"
    And I should see "Bulgarian body"
