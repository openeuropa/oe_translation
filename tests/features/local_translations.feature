@api @translation
Feature: Local translations
  In order to easily have multilingual content
  As a translator
  I need to be able to translate content

  Scenario: Translate content.
    Given a translatable node with the "My title" title and "My body" body and multiple links
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
    And the translation form element for the "Link Uri (1)" field should contain "https://example.com"
    And the translation form element for the "Link Title (1)" field should contain "Example"
    And the translation form element for the "Link Uri (2)" field should contain "/node"
    And the translation form element for the "Link Title (2)" field should contain "Node"

    When I fill in the translation form element for the "Title" field with "Bulgarian title"
    And I fill in the translation form element for the "Body" field with "Bulgarian body"
    And I fill in the translation form element for the "Link Uri (1)" field with "https://example.com/bg"
    And I fill in the translation form element for the "Link Title (1)" field with "Example BG"
    And I fill in the translation form element for the "Link Uri (2)" field with "/bg/node"
    And I fill in the translation form element for the "Link Title (2)" field with "Node BG"
    # And I press "Save and come back later"
    # Then I should see "Translations of My title"
    # And I should see "Not translated" in the "Bulgarian" row

    # Finalize translation.
    # When I click "Edit local translation" in the "Bulgarian" row
    # Then the translation form element for the "Title" field should contain "Bulgarian title"
    # And the translation form element for the "Body" field should contain "Bulgarian body"

    When I press "Save and complete translation"
    Then I should see "Translations of My title"
    And I should not see "Not translated" in the "Bulgarian" row
    And I should see "Published" in the "Bulgarian" row
    When I click "Bulgarian title" in the "Bulgarian" row
    Then I should see "Bulgarian title"
    And I should see "Bulgarian body"

    # Create a new translation for the same language.
    When I click "Translate"
    And I click "Translate locally" in the "Bulgarian" row
    Then the translation form element for the "Title" field should contain "Bulgarian title"
    And the translation form element for the "Body" field should contain "Bulgarian body"
    And the translation form element for the "Link Uri (1)" field should contain "https://example.com/bg"
    And the translation form element for the "Link Title (1)" field should contain "Example BG"
    And the translation form element for the "Link Uri (2)" field should contain "/bg/node"
    And the translation form element for the "Link Title (2)" field should contain "Node BG"

    When I fill in the translation form element for the "Title" field with "Bulgarian title 2"
    And I press "Save and finish translation"
    Then I should see "Translations of Bulgarian title 2"
    When I click "Bulgarian title 2" in the "Bulgarian" row
    Then I should see "Bulgarian title 2"
    And I should see "Bulgarian body"

  Scenario: Preview translations
    Given a translatable node with the "My title" title and "My body" body and multiple links
    And I am logged in as a user with the "translator" role
    When I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I click "Translate locally" in the "French" row
    And I fill in the translation form element for the "Title" field with "Bulgarian title"
    And I fill in the translation form element for the "Body" field with "Bulgarian body"
    And I press "Preview"
    Then I should see "Bulgarian title" in the "title" region
    And I should see "Bulgarian body" in the "node content" region
