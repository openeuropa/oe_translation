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
    And I am logged in as a user with the "oe_translator" role
    When I visit "the content administration page"
    And I click "My title"
    Then I should see the link "Translate"

    # Request the translation for two languages
    When I click "Translate"
    And I select the languages "Bulgarian, German" in the language list
    And I press "Request a DGT translation for the selected languages"
    Then I should see "Send request to DG Translation for My title in Bulgarian, German"

    When I fill in the the "first" "Author" field with "john"
    And I fill in "Secretary" with "john"
    And I fill in "Contact" with "john"
    And I fill in the the "first" "Responsible" field with "john"
    And I fill in the the "second" "Responsible" field with "john"
    And I fill in the the "second" "Author" field with "john"
    And I fill in "Requester" with "john"
    And I press "Send request"
    Then I should see the error message "Requested delivery date field is required."
    And I fill in "Requested delivery date" with "12/01/2019"
    And I press "Send request"
    Then I should see the error message "Requested delivery date cannot be in the past."
    When I fill in "Requested delivery date" with "12/01/2050"
    And I press "Send request"
    Then I should see "The request has been sent to DGT."
    And I see the correct DGT Poetry request reference for "My title"
    And I should see "Submitted to Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "German" row
    And I should see "None" in the "Danish" row
    And I should not see the button "Request a DGT translation for the selected languages"
    And I should not see the button "Request a DGT translation update for the selected languages"
    And I should see "No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated."
    And the Poetry request jobs to translate "My title" should get created for "Bulgarian, German"

    # The translation gets accepted in Poetry
    When the Poetry translation request of "My title" in "Bulgarian, German" gets accepted
    And I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I see the correct DGT Poetry request reference for "My title"
    And I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "German" row
    And I should see "None" in the "Danish" row
    And I should not see the button "Request a DGT translation for the selected languages"
    And I should not see "No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated."
    And I should see the button "Request a DGT translation update for the selected languages"

    # The first translation gets sent from Poetry
    When the Poetry translations of "My title" in "Bulgarian" are received from Poetry

    # Accept a translation job
    When I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I see the correct DGT Poetry request reference for "My title"
    # Still one job left to come from Poetry
    And I should not see the button "Request a DGT translation for the selected languages"
    And I should see the button "Request a DGT translation update for the selected languages"
    And I should see "Ongoing in Poetry" in the "German" row
    And I click "Review translation" in the "Bulgarian" row
    Then I should see "Job item My title"
    When I press "Accept translation"
    Then I should see "The translation for My title has been accepted as My title - bg"
    And I should see "Translations of My title"

    # Go to the translated page
    When I click "My title - bg"
    Then I should see "My title - bg"
    And I should see "My body - bg"

    # The other translation gets sent from Poetry
    When the Poetry translations of "My title" in "German" are received from Poetry
    And I visit "the content administration page"
    And I click "My title"
    And I click "Translate"
    And I should see the button "Request a DGT translation for the selected languages"
    And I should not see "No translation requests to DGT can be made until the ongoing ones have been accepted and/or translated."
    And I should not see the button "Request a DGT translation update for the selected languages"
    And I should not see "Ongoing DGT Poetry request reference:"

  @cleanup:tmgmt_job @cleanup:tmgmt_job_item @poetry
  Scenario: Translate content and request an update.
    Given oe_demo_translatable_page content:
      | title              | field_oe_demo_translatable_body |
      | My title to update | My body to update               |
    And I am logged in as a user with the "oe_translator" role
    When I visit "the content administration page"
    And I click "My title to update"
    And I click "Translate"
    And I select the languages "Bulgarian" in the language list
    And I press "Request a DGT translation for the selected languages"
    Then I should see "Send request to DG Translation for My title to update in Bulgarian"

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
    And I see the correct DGT Poetry request reference for "My title to update"
    And I should see "Submitted to Poetry" in the "Bulgarian" row

    # The translation gets accepted in Poetry
    When the Poetry translation request of "My title to update" in "Bulgarian" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row
    And the "Bulgarian" language checkbox in the language list is checked

    # Make an update request
    When I select the languages "German" in the language list
    And I press "Request a DGT translation update for the selected languages"
    Then I should see "Send update request to DG Translation for My title to update in German, Bulgarian"
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
    And I see the correct DGT Poetry request reference for "My title to update"
    And I should see "Submitted to Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "German" row
    And I should not see the button "Request a DGT translation update for the selected languages"

    # The translations get accepted in Poetry
    When the Poetry translation request of "My title to update" in "Bulgarian, German" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "German" row
    And I should see the button "Request a DGT translation update for the selected languages"

    # Translation are sent from Poetry
    When the Poetry translations of "My title to update" in "Bulgarian, German" are received from Poetry
    And I reload the page
    Then I should not see "Ongoing DGT Poetry request reference:"
    When I click "Review translation" in the "German" row
    And I press "Accept translation"
    Then I should see "The translation for My title to update has been accepted as My title to update - de"
    When I click "Review translation" in the "Bulgarian" row
    And I press "Accept translation"
    Then I should see "The translation for My title to update has been accepted as My title to update - bg"

    # Go to the translated pages
    When I click "My title to update - bg"
    Then I should see "My title to update - bg"
    And I should see "My body to update - bg"
    When I click "Deutsch"
    Then I should see "My title to update - de"
    And I should see "My body to update - de"

  @cleanup:tmgmt_job @cleanup:tmgmt_job_item @poetry
  Scenario: Cancel languages and requests.
    Given oe_demo_translatable_page content:
      | title                 | field_oe_demo_translatable_body |
      | My title to translate | My body to translate            |
    And I am logged in as a user with the "oe_translator" role

    # Send a translation request
    When I visit "the content administration page"
    And I click "My title to translate"
    And I click "Translate"
    And I select the languages "Bulgarian, Czech" in the language list
    And I press "Request a DGT translation for the selected languages"
    And I fill in "Requested delivery date" with "05/04/2050"
    And I fill in the the "first" "Author" field with "john"
    And I fill in "Secretary" with "john"
    And I fill in "Contact" with "john"
    And I fill in the the "first" "Responsible" field with "john"
    And I fill in the the "second" "Responsible" field with "john"
    And I fill in the the "second" "Author" field with "john"
    And I fill in "Requester" with "john"
    And I press "Send request"
    Then I should see "Submitted to Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "Czech" row

    # The translation gets accepted in Poetry
    When the Poetry translation request of "My title to translate" in "Bulgarian, Czech" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "Czech" row

    # Cancel a language and leave other ongoing
    When Poetry updates the status for "My title to translate" as "Ongoing" with the following individual statuses
      | language  | status    |
      | Bulgarian | Ongoing   |
      | Czech     | Cancelled |
    And I reload the page
    Then I should see "Cancelled in Poetry" in the "Czech" row
    And I should see "Ongoing in Poetry" in the "Bulgarian" row

    # Make an update request
    When I select the languages "German" in the language list
    And I press "Request a DGT translation update for the selected languages"
    And I fill in "Requested delivery date" with "05/04/2050"
    And I fill in the the "first" "Author" field with "john"
    And I fill in "Secretary" with "john"
    And I fill in "Contact" with "john"
    And I fill in the the "first" "Responsible" field with "john"
    And I fill in the the "second" "Responsible" field with "john"
    And I fill in the the "second" "Author" field with "john"
    And I fill in "Requester" with "john"
    And I press "Send request"
    Then I should see "Submitted to Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "German" row
    And I should not see "Cancelled in Poetry" in the "Czech" row

    # The translations get accepted in Poetry
    When the Poetry translation request of "My title to translate" in "Bulgarian, German" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "German" row

    # Cancel the request
    When Poetry updates the status for "My title to translate" as "Cancelled" with the following individual statuses
      | language  | status    |
      | Bulgarian | Ongoing   |
      | German    | Cancelled |
    And I reload the page
    Then I should see "Cancelled in Poetry" in the "Bulgarian" row
    And I should see "Cancelled in Poetry" in the "German" row

    # Send a new translation request
    When I select the languages "Spanish" in the language list
    And I press "Request a DGT translation for the selected languages"
    And I fill in "Requested delivery date" with "05/04/2050"
    And I fill in the the "first" "Author" field with "john"
    And I fill in "Secretary" with "john"
    And I fill in "Contact" with "john"
    And I fill in the the "first" "Responsible" field with "john"
    And I fill in the the "second" "Responsible" field with "john"
    And I fill in the the "second" "Author" field with "john"
    And I fill in "Requester" with "john"
    And I press "Send request"
    Then I should see "The request has been sent to DGT."
    And I should not see "Cancelled in Poetry" in the "Bulgarian" row
    And I should not see "Cancelled in Poetry" in the "Czech" row
    And I should see "Submitted to Poetry" in the "Spanish" row

  @cleanup:tmgmt_job @cleanup:tmgmt_job_item @poetry
  Scenario: Translate content and add languages.
    Given oe_demo_translatable_page content:
      | title      | field_oe_demo_translatable_body |
      | Some title | Some body                       |
    And I am logged in as a user with the "oe_translator" role
    When I visit "the content administration page"
    And I click "Some title"
    And I click "Translate"
    And I select the languages "Bulgarian" in the language list
    And I press "Request a DGT translation for the selected languages"
    Then I should see "Send request to DG Translation for Some title in Bulgarian"

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
    And I should see "Submitted to Poetry" in the "Bulgarian" row

    # The translation gets accepted in Poetry
    When the Poetry translation request of "Some title" in "Bulgarian" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row

    # Make a request to add a language
    When I press "Add extra languages to the ongoing DGT request"
    Then I should see the error message "You need to select at least one extra language to add to the request."

    When I select the languages "German" in the language list
    And I press "Add extra languages to the ongoing DGT request"
    Then I should see "Send extra languages to the previous request for Some title: German"
    When I fill in "Requested delivery date" with "05/04/2050"
    And I press "Send request"
    Then I should see "The request has been sent to DGT."
    And I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Submitted to Poetry" in the "German" row
    And I should not see the button "Add extra languages to the ongoing DGT request"

    # The translations get accepted in Poetry
    When the Poetry translation request of "Some title" in "Bulgarian, German" gets accepted
    And I reload the page
    Then I should see "Ongoing in Poetry" in the "Bulgarian" row
    And I should see "Ongoing in Poetry" in the "German" row
    And I should see the button "Add extra languages to the ongoing DGT request"

    # Translation are sent from Poetry
    When the Poetry translations of "Some title" in "Bulgarian, German" are received from Poetry
    And I reload the page
    And I click "Review translation" in the "German" row
    When I press "Accept translation"
    Then I should see "The translation for Some title has been accepted as Some title - de"
    And I click "Review translation" in the "Bulgarian" row
    When I press "Accept translation"
    Then I should see "The translation for Some title has been accepted as Some title - bg"

    # Go to the translated pages
    When I click "Some title - bg"
    Then I should see "Some title - bg"
    And I should see "Some body - bg"
    When I click "Deutsch"
    Then I should see "Some title - de"
    And I should see "Some body - de"
