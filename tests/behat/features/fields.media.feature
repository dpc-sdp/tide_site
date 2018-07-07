@tide
Feature: Site fields on media

  As a site administrator, I want to know that Site field automatically added
  to new media types upon creation.

  @api @javascript
  Scenario: Site field is added to media types upon creation.
    Given no "mymediatest" media type
    When I am logged in as a user with the "administer media, administer media types, create media, administer media fields, administer media form display, administer media display" permission
    And I go to "admin/structure/media/add"
    Then I should see "Add media type"

    When I fill in "Name" with "mymediatest"
    And I wait for "10" seconds
    And I select "File" from "Media source"
    And I wait for AJAX to finish
    And I wait for "10" seconds
    And I press "Save"
    And save screenshot
    Then I should see the following success messages:
      | success messages                                                               |
      | The media type mymediatest has been added.                                     |
      | Added field field_media_site to the mymediatest media entity and form display. |

    When I go to "admin/structure/media/manage/mymediatest/fields"
    Then I should see the text "Site" in the "field_media_site" row
    Then I should not see the text "Primary Site"

    When I go to "admin/structure/media/manage/mymediatest/form-display"
    And the "#edit-fields-field-media-site-type option[selected='selected']" element should contain "Check boxes/radio buttons"

    When I go to "admin/structure/media/manage/mymediatest/display"
    And the "#edit-fields-field-media-site-region option[selected='selected']" element should contain "Disabled"

    When I go to "media/add/mymediatest"
    And I should see an "input#edit-name-0-value" element
    And I should see an "input#edit-name-0-value.required" element
    And I should see an "#edit-field-media-site--wrapper" element
    And I should not see an "#edit-field-media-site--wrapper.required" element
    And I should not see an "#edit-field-media-primary-site--wrapper" element
    And I should not see a "#edit-field-media-primary-site--wrapper.required" element

    And no "mymediatest" media type
