@jsonapi
Feature: Link Enhancer with Site

  @api @suggest
  Scenario: Request to "test" individual endpoint with results.
    Given vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name        | parent | tid   |
      | Test Site 1 | 0      | 10001 |
      | Test Site 2 | 0      | 10002 |

    When I am logged in as a user with the "administer taxonomy" permission
    And I go to "taxonomy/term/10001/edit"
    And I fill in "Domains" with:
    """
    prod.site1.com
    dev.site1.com
    """
    Then I press the "Save" button

    Then I go to "taxonomy/term/10002/edit"
    And I fill in "Domains" with:
    """
    prod.site2.com
    dev.site2.com
    """
    Then I press the "Save" button

    Given test content:
      | title         | path         | moderation_state | uuid                                | nid    | field_test_link             | field_node_site          | field_node_primary_site |
      | [TEST] Page 1 | /test-page-1 | published        | 99999999-aaaa-bbbb-ccc-000000000001 | 999991 | Page 2 - entity:node/999992 | Test Site 1              | Test Site 1             |
      | [TEST] Page 2 | /test-page-2 | published        | 99999999-aaaa-bbbb-ccc-000000000002 | 999992 | Page 3 - entity:node/999993 | Test Site 1              | Test Site 1             |
      | [TEST] Page 3 | /test-page-3 | published        | 99999999-aaaa-bbbb-ccc-000000000003 | 999993 | Page 4 - entity:node/999994 | Test Site 1, Test Site 2 | Test Site 1             |
      | [TEST] Page 4 | /test-page-4 | published        | 99999999-aaaa-bbbb-ccc-000000000004 | 999994 | Page 1 - entity:node/999991 | Test Site 2              | Test Site 2             |
      | [TEST] Page 5 | /test-page-4 | published        | 99999999-aaaa-bbbb-ccc-000000000005 | 999995 | Page 3 - entity:node/999993 | Test Site 2, Test Site 1 | Test Site 2             |

    Given I am an anonymous user

    # Page 1 (site 1) with a reference to Page 2 (site 1).
    # Current site is site 1. Link field should have relative URL.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000001?site=10001"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 2"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should not contain "prod.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "http"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 2 (site 1) with a reference to Page 3 (site 1, site 2).
    # Current site is site 1. Link field should have relative URL.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000002?site=10001"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 3"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should not contain "prod.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "http"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 3 (site 1, site 2) with a reference to Page 4 (site 2).
    # Current site is site 1. Link field should have full URL of site 2.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000003?site=10001"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000004"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 4"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should contain "prod.site2.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site2.com"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 3 (site 1, site 2) with a reference to Page 4 (site 2).
    # Current site is site 2. Link field should have relative URL.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000003?site=10002"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000004"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 4"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should not contain "prod.site2.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site2.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "http"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 4 (site 2) with a reference to Page 1 (site 1).
    # Current site is site 2. Link field should have full URL of site 1.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000004?site=10002"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000004"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 1"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should contain "prod.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site1.com"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 5 (site 2, site 1) with a reference to Page 3 (site 1, site 2).
    # Current site is site 1. Link field should have relative URL.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000005?site=10001"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000005"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 3"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should not contain "prod.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site1.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "http"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist

    # Page 5 (site 2, site 1) with a reference to Page 3 (site 1, site 2).
    # Current site is site 2. Link field should have relative URL.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000005?site=10002"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "links.self" should exist
    And the JSON node "links.self.href" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000005"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 3"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.url" should not contain "prod.site2.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "dev.site2.com"
    And the JSON node "data.attributes.field_test_link.url" should not contain "http"
    And the JSON node "data.attributes.field_test_link.origin_url" should exist
