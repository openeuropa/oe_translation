@api @translation
Feature: Poetry translations
  In order to easily have multilingual content
  As a translator
  I need to be able to translate content using the Poetry service

  @cleanup:tmgmt_job @cleanup:tmgmt_job_item @poetry
  Scenario: Translate content.
    Given oe_demo_translatable_page content:
      | title    | field_oe_demo_translatable_body |
      | My title | My body                         |
    And I am logged in as a user with the "translator" role
    When I visit "the content administration page"
    And I click "My title"
    Then I should see the link "Translate"

    # Request the translation for two languages
    When I click "Translate"
    And I select the languages "Bulgarian, German" in the language list
    And I press "Request DGT translation for the selected languages"
    Then I should see "Send request to DG Translation for My title in Bulgarian, German"

    When I fill in "Requested delivery date" with "05/04/2050"
    And I fill in the the "first" "Author" field with "john"
    And I fill in "Secretary" with "john"
    And I fill in "Contact" with "john"
    And I fill in the the "first" "Responsible" field with "john"
    And I fill in the the "second" "Responsible" field with "john"
    And I fill in the the "second" "Author" field with "john"
    And I fill in "Requester" with "john"
    And I press "Send request"
    Then I should see "The request has been sent to DGT."
    And the Poetry request jobs to translate "My title" should get created for "Bulgarian, German"
    And I should see "Submitted to Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "German" row
    And I should see "None" in the "Danish" row

    # The translation gets accepted in Poetry
    Given the Poetry translation request of "My title" in "Bulgarian, German" gets accepted
    And I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "German" row
    And I should see "None" in the "Danish" row

    # The translation gets sent from Poetry
    Given the Poetry translations of "My title" in "Bulgarian, German" get sent by Poetry

    # Accept a translation job
    When I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I click "Review translation" in the "Bulgarian" row
    Then I should see "Job item My title"
    When I press "Accept translation"
    Then I should see "The translation for My title has been accepted as My title - bg"

    # Go to the translated page
    When I click "My title - bg"
    Then I should see "My title - bg"
    And I should see "My body - bg"
