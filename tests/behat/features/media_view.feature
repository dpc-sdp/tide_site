@tide
Feature: Media view filter addition

  As a site admin, I want to be able to filter media based on Site.

  Background:
    Given vocabulary "sites" with name "Sites" exists

  @api @javascript
  Scenario: Media view has Site filter
    Given I am logged in as an administrator
    When I visit "admin/content/media"
    And I should see "Site" in the "label[for=edit-field-media-site-target-id]" element
