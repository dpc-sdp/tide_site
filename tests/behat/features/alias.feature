@tide
Feature: Node path alias with site prefix

  @api
  Scenario: Check for Site URL Path Settings
    Given topic terms:
      | name       |
      | Test Topic |
    Given sites terms:
      | name        | parent | tid    |
      | Test Site 1 | 0      | 999991 |
      | Test Site 2 | 0      | 999992 |
      | Test Site 3 | 0      | 999993 |
    Given test content:
      | title               | moderation_state | field_node_site          | field_node_primary_site | field_topic | body | nid    |
      | [TEST] Test content | published        | Test Site 1, Test Site 2 | Test Site 1             | Test Topic  | Test | 999999 |
    Given I am logged in as a user with the "approver" role

    When I edit test "[TEST] Test content"

    Then I should see the text "URL PATH SETTINGS"
    # The default path/pathauto form elements should be disabled.
    And I should not see an "#edit-path-settings" element
    And I should not see the text "Specify an alternative path by which this data can be accessed."
    And I should not see an "#edit-path-0-alias" element
    And I should not see the text "Generate automatic URL alias"
    And I should not see an "#edit-path-0-pathauto" element
    # Site path aliases for Site 1 and Site 2 should be available.
    And I should see the text "/site-999991/test-test-content"
    And I should see the text "/site-999992/test-test-content"
    And I should not see the text "/site-999993/test-test-content"

    # Remove Site 2 and add Site 3 to the node.
    Then I uncheck the box "Test Site 2"
    And I check the box "Test Site 3"
    And I select "Published" from "Change to"
    And I press "Save"
    And I edit test "[TEST] Test content"
    # Site path aliases for Site 1 and Site 3 should be available.
    And I should see the text "/site-999991/test-test-content"
    And I should not see the text "/site-999992/test-test-content"
    And I should see the text "/site-999993/test-test-content"

    # Add a custom alias for the node.
    Then I visit "/admin/config/search/path/add"
    And I fill in "Existing system path" with "/node/999999"
    And I fill in "Path alias" with "/another-alias-for-test"
    And I press "Save"
    And I edit test "[TEST] Test content"
    # Site path aliases for Site 1 and Site 3 should be available.
    And I should see the text "/site-999991/test-test-content"
    And I should not see the text "/site-999992/test-test-content"
    And I should see the text "/site-999993/test-test-content"
    # New aliases should also exist for Site 1 and Site 3.
    And I should see the text "/site-999991/another-alias-for-test"
    And I should not see the text "/site-999992/another-alias-for-test"
    And I should see the text "/site-999993/another-alias-for-test"
