Feature: Site fields on media

  As a site administrator, I want to know that Site field automatically added
  to new media types upon creation.

  @api @javascript @suggest
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
    And I should see an "#edit-field-media-site--wrapper.required" element
    And I should not see an "#edit-field-media-primary-site--wrapper" element
    And I should not see a "#edit-field-media-primary-site--wrapper.required" element

  @api @suggest
  Scenario: Ensure that sites field are required.
    Given sites terms:
      | name                 | parent          | tid   | uuid                                  |
      | Test Site 1          | 0               | 10010 | 11dede11-10c0-111e1-1100-000000000031 |
      | Test Section 11      | Test Site 1     | 10011 | 11dede11-10d0-111e1-1100-000000000032 |
      | Test Section 12      | Test Site 1     | 10014 | 11dede11-10g0-111e1-1100-000000000035 |
      | Test Site 2          | 0               | 10015 | 11dede11-10h0-111e1-1100-000000000036 |
      | Test Site 3          | 0               | 10016 | 11dede11-10i0-111e1-1100-000000000037 |
    And users:
      | name        | status | uid    | mail                    | pass         | field_user_site | roles  |
      | test.editor |      1 | 999999 | test.editor@example.com | L9dx9IJz3'M* | Test Section 11 | Editor |

    When I am logged in as "test.editor"
    And I go to "media/add/audio"
    Then save screenshot
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
    When I go to "media/add/document"
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
    When I go to "media/add/embedded_video"
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
    When I go to "media/add/file"
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
    When I go to "media/add/image"
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
    When I go to "media/add/video"
    And I should see an "fieldset#edit-field-media-site--wrapper.required" element
