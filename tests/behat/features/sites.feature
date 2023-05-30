Feature: Sites taxonomy vocabulary

  As a site administrator, I want to know that Sites vocabulary exists.

  @api @skipped
  Scenario: Sites taxonomy vocabulary exists
    Given vocabulary "sites" with name "Sites" exists
    When I am logged in as a user with the "administer taxonomy" permission
    And I go to "admin/structure/taxonomy/manage/sites/add"
    Then I see field "Name"
    And I see field "Slogan"
    And I see field "Logo"
    And I see field "Footer text"
    And I see field "Domains"
    And I see field "Main menu"
    And I see field "Footer menu"
    And I see field "Homepage"
    And I don't see field "Description"

    And I should see text matching "Footer logos"
    And I should see text matching "No Paragraph added yet.."

    And I see field "Show Exit Site?"
    And I should see an "input#edit-field-site-show-exit-site-value" element
    And I should not see an "input#edit-field-site-show-exit-site-value.required" element

