@tide
Feature: Workflow states and transitions

  Ensure that workflow states and transitions are configured as expected

  @api
  Scenario: Editor creates draft content and sends to Review, Approver reviews and publishes.
    Given I am logged in as a user with the Editor role
    When I go to "node/add/test"
    And the response status code should be 200

    # Populate content fields and create content piece in Draft.
    When I fill in "Title" with "[TEST] Editor Test title"
    And I fill in "Body" with "Test body content"
    And I select "Draft" from "Save as"
    And I press "Save"
    And I save screenshot
    Then I should see the success message "[TEST] Editor Test title has been created."
    And I should see a "article.node--unpublished" element

    # Change state from Draft to Needs Review.
    When I edit test "[TEST] Editor Test title"
    Then the response status code should be 200
    And I select "Needs Review" from "Change to"
    And I press "Save"
    Then I should see the success message "[TEST] Editor Test title has been updated."
    And I should see a "article.node--unpublished" element

    When I go to "admin/content/moderated"
    And the response status code should be 200
    And I should see the text "[TEST] Editor Test title"
    And I should see the text "Needs Review" in the "[TEST] Editor Test title" row
