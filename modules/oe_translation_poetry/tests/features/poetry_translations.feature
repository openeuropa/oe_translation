@api @translation
Feature: Poetry translations
  In order to have official multilingual content
  As a translator
  I need to be able to send translation requests to DGT

  Scenario: Manage requests.
    Given oe_demo_translatable_page content:
      | title    | field_oe_demo_translatable_body | demo_link_field                             |
      | My title | My body                         | Example - https://example.com, Node - /node |
    And I am logged in as a user with the "translator" role
    When I visit "the content administration page"
    And I click "My title"
    Then I should see the link "Translate"

    # Create a translation request.
    When I click "Translate"
    And I check "bg"
    And I check "cs"
    And I click "Request DGT translation for the selected languages"
    Then I should see "Send request to DG Translation (bg, cs)"

    # Return to the translation checkout form.
    When I visit "the content administration page"
    And I click "My title"
    When I click "Translate"
    Then I should see "Finish translation request to DGT (bg, cs)"

    # Delete an unprocessed job.
    When I click "Delete unprocessed job" in the "Czech" row
    And I click "Delete"
    And I click "Finish translation request to DGT (bg)"
    Then I should see "Send request to DG Translation (bg)"

    # Return to the translation checkout form and remove all unprocessed jobs.
    When I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I click "Delete unprocessed job" in the "Bulgarian" row
    And I click "Delete"
    Then I should see "Request DGT translation for the selected languages"
