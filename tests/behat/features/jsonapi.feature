@jsonapi
Feature: JSONAPI exposure

  As a site administrator, I want to know that Sites vocabulary and terms are
  exposed through JSON API.

  Background:
    Given "sites" terms:
      | name             | field_site_slogan:value | field_site_footer_text:value | field_site_domains |
      | [TEST] Site name | Test site slogan        | test site footer             | www.example.com    |

  @api @suggest
  Scenario: Request to Sites endpoint to get all information about sites
    Given I am an anonymous user
    When I send a GET request to "api/v1/taxonomy_term/sites?sort=-tid"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "jsonapi.version" should be equal to "1.0"
    And the JSON node "links.self.href" should contain "api/v1/taxonomy_term/sites"
    And the JSON node "meta.count" should exist
    And the JSON node "data" should exist
    And the JSON node "data[0].type" should be equal to "taxonomy_term--sites"
    And the JSON node "data[0].id" should exist
    And the JSON node "data[0].attributes.drupal_internal__tid" should exist
    And the JSON node "data[0].attributes.field_site_domains" should be equal to "www.example.com"
    And the JSON node "data[0].attributes.field_site_slogan.value" should be equal to "Test site slogan"
    And the JSON node "data[0].attributes.field_site_footer_text.value" should be equal to "test site footer"
    And the JSON node "data[0].relationships.field_site_main_menu" should exist
    And the JSON node "data[0].relationships.field_site_footer_menu" should exist
    And the JSON node "data[0].relationships.field_site_footer_main_menu" should exist
