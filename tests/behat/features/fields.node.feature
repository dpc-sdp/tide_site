Feature: Site and Primary Site fields on nodes

  As a site administrator, I want to know that Site and Primary Site fields
  automatically added to new content types upon creation.

  @api
  Scenario: Site and Primary Site fields are added to content types upon creation.
    Given no "sitetest" content type
    When I am logged in as a user with the "administer content types, administer node fields, administer node display, administer node form display" permission
    And I go to "admin/structure/types/add"
    Then the response status code should be 200

    When I fill in "Name" with "sitetest"
    And I fill in "Machine-readable name" with "sitetest"
    And I press "Save and manage fields"
    And I should see the text "Site" in the "field_node_site" row
    And I should see the text "Primary Site" in the "field_node_primary_site" row
    And I should see the following success messages:
      | success messages                                                                  |
      | The content type sitetest has been added.                                         |
      | Added field field_node_site to the sitetest node entity and form display.         |
      | Added field field_node_primary_site to the sitetest node entity and form display. |

    When I go to "admin/structure/types/manage/sitetest/form-display"
    Then the response status code should be 200
    And the "#edit-fields-field-node-site-region option[selected='selected']" element should contain "Content"
    And the "#edit-fields-field-node-primary-site-region option[selected='selected']" element should contain "Content"

    When I go to "admin/structure/types/manage/sitetest/display"
    Then the response status code should be 200
    And the "#edit-fields-field-node-site-region option[selected='selected']" element should contain "Disabled"
    And the "#edit-fields-field-node-primary-site-region option[selected='selected']" element should contain "Disabled"

    When I go to "node/add/sitetest"
    Then I see field "Title"
    And I should see an "input#edit-title-0-value.required" element
    And I see field "Body"
    And I should see a "textarea#edit-body-0-value" element
    And I should see an "#edit-field-node-primary-site--wrapper" element
    And I should see a "#edit-field-node-primary-site--wrapper.required" element
    And I should see an "#edit-field-node-site--wrapper" element
    And I should see an "#edit-field-node-site--wrapper.required" element

    And no "sitetest" content type

  @api
  Scenario: Site and Primary Site fields show terms only from specific depths.
    Given no "sitetest" content type
    And vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name                 | parent          |
      | Test Site 1          | 0               |
      | Test Section 11      | Test Site 1     |
      | Test Sub Section 111 | Test Section 11 |
      | Test Sub Section 112 | Test Section 11 |
      | Test Section 12      | Test Site 1     |
      | Test Site 2          | 0               |
      | Test Site 3          | 0               |

    When I am logged in as a user with the "administer content types, administer node fields, administer node display, administer node form display" permission
    And I go to "admin/structure/types/add"
    Then the response status code should be 200

    When I fill in "Name" with "sitetest"
    And I fill in "Machine-readable name" with "sitetest"
    And I press "Save and manage fields"
    And I should see the following success messages:
      | success messages                          |
      | The content type sitetest has been added. |

    When I go to "node/add/sitetest"
    And the response status code should be 200

    Then the "#edit-field-node-site" element should contain "Test Site 1"
    Then the "#edit-field-node-site" element should contain "Test Section 11"
    Then the "#edit-field-node-site" element should not contain "Test Sub Section 111"
    Then the "#edit-field-node-site" element should contain "Test Section 12"
    Then the "#edit-field-node-site" element should contain "Test Site 2"
    Then the "#edit-field-node-site" element should contain "Test Site 3"

    Then the "#edit-field-node-primary-site" element should contain "Test Site 1"
    Then the "#edit-field-node-primary-site" element should not contain "Test Section 11"
    Then the "#edit-field-node-primary-site" element should not contain "Test Sub Section 111"
    Then the "#edit-field-node-primary-site" element should not contain "Test Section 12"
    Then the "#edit-field-node-primary-site" element should contain "Test Site 2"
    Then the "#edit-field-node-primary-site" element should contain "Test Site 3"

    And no "sitetest" content type
