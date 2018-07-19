@tide
Feature: Node path alias with site prefix in JSONAPI.

  @api @suggest
  Scenario: Check for Site Alias in JSONAPI response
    Given sites terms:
      | name        | parent | tid    | field_site_domains |
      | Test Site 1 | 0      | 100001 | site1.test         |
      | Test Site 2 | 0      | 100002 | site2.test         |
      | Test Site 3 | 0      | 100003 | site3.test         |
    Given test content:
      | nid    | uuid                                | title         | moderation_state | field_node_site          | field_node_primary_site | field_test_link             | field_test_reference | body:value                                                                                                                                            | body:format |
      | 999991 | 99999999-aaaa-bbbb-ccc-000000000001 | [TEST] Page 1 | published        | Test Site 1              | Test Site 1             | Page 2 - entity:node/999992 |                      | <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="99999999-aaaa-bbbb-ccc-000000000002" href="/node/999992">Page 2</a> | rich_text   |
      | 999992 | 99999999-aaaa-bbbb-ccc-000000000002 | [TEST] Page 2 | published        | Test Site 1, Test Site 2 | Test Site 2             | Page 3 - entity:node/999993 | [TEST] Page 1        | <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="99999999-aaaa-bbbb-ccc-000000000003" href="/node/999993">Page 3</a> | rich_text   |
      | 999993 | 99999999-aaaa-bbbb-ccc-000000000003 | [TEST] Page 3 | published        | Test Site 2              | Test Site 2             | Page 1 - entity:node/999991 | [TEST] Page 1        | <a data-entity-substitution="canonical" data-entity-type="node" data-entity-uuid="99999999-aaaa-bbbb-ccc-000000000001" href="/node/999991">Page 1</a> | rich_text   |

    And I am an anonymous user

    # Test Routing API.

    # Query Page 1 (Site 1). Current site is Site 1.
    When I send a GET request to "api/v1/route?site=100001&path=/test-page-1"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data.bundle" should contain "test"
    And the JSON node "data.endpoint" should contain "api/v1/node/test"
    And the JSON node "data.uuid" should be equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "errors" should not exist

    # Query Page 1 (Site 1). Current site is Site 2.
    When I send a GET request to "api/v1/route?site=100002&path=/test-page-1"
    Then the rest response status code should be 404
    And the response should be in JSON
    And the JSON node "errors" should exist

    # Query Page 2 (Site 1, Site 2). Current site is Site 1.
    When I send a GET request to "api/v1/route?site=100001&path=/test-page-2"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data.bundle" should contain "test"
    And the JSON node "data.endpoint" should contain "api/v1/node/test"
    And the JSON node "data.uuid" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "errors" should not exist

    # Query Page 2 (Site 1, Site 2). Current site is Site 2.
    When I send a GET request to "api/v1/route?site=100002&path=/test-page-2"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data.bundle" should contain "test"
    And the JSON node "data.endpoint" should contain "api/v1/node/test"
    And the JSON node "data.uuid" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "errors" should not exist

    # Test JSONAPI Entity API.

    # Check Page 1 (Site 1). Current site is Site 1.
    # Link field should have relative URL of Page 2.
    # Body should have relative URL of Page 2.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000001?site=100001&include=field_test_reference"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "data.attributes.path" should exist
    And the JSON node "data.attributes.path.alias" should be equal to "/test-page-1"
    And the JSON node "data.attributes.path.url" should be equal to "/test-page-1"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 2"
    And the JSON node "data.attributes.field_test_link.url" should be equal to "/test-page-2"
    And the JSON node "data.attributes.body" should exist
    And the JSON node "data.attributes.body.processed" should contain "href="/test-page-2""

    # Check Page 2 (Site 1). Current site is Site 1.
    # Link field should have absolute URL of Page 3 on Site 2.
    # Body should have absolute URL of Page 3 on Site 2.
    # Referenced node should have relative URL of Page 1.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000002?site=100001&include=field_test_reference"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "data.attributes.path" should exist
    And the JSON node "data.attributes.path.alias" should be equal to "/test-page-2"
    And the JSON node "data.attributes.path.url" should be equal to "/test-page-2"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 3"
    And the JSON node "data.attributes.field_test_link.url" should be equal to "http://site2.test/test-page-3"
    And the JSON node "data.attributes.body" should exist
    And the JSON node "data.attributes.body.processed" should contain "href="http://site2.test/test-page-3""
    And the JSON node "included[0]" should exist
    And the JSON node "included[0].attributes.path" should exist
    And the JSON node "included[0].attributes.path.url" should be equal to "/test-page-1"

    # Check Page 2 (Site 1). Current site is Site 2.
    # Link field should have relative URL of Page 3.
    # Body should have relative URL of Page 3.
    # Referenced node should have absolute URL of Page 1 on Site 1.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000002?site=100002&include=field_test_reference"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "data.attributes.path" should exist
    And the JSON node "data.attributes.path.alias" should be equal to "/test-page-2"
    And the JSON node "data.attributes.path.url" should be equal to "/test-page-2"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 3"
    And the JSON node "data.attributes.field_test_link.url" should be equal to "/test-page-3"
    And the JSON node "data.attributes.body" should exist
    And the JSON node "data.attributes.body.processed" should contain "href="/test-page-3""
    And the JSON node "included[0]" should exist
    And the JSON node "included[0].attributes.path" should exist
    And the JSON node "included[0].attributes.path.url" should be equal to "http://site1.test/test-page-1"

    # Check Page 3 (Site 2). Current site is Site 2.
    # Link field should have absolute URL of Page 1 on Site 1.
    # Body should have absolute URL of Page 1 on Site 1.
    # Referenced node should have absolute URL of Page 1 on Site 1.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000003?site=100002&include=field_test_reference"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000003"
    And the JSON node "data.attributes.path" should exist
    And the JSON node "data.attributes.path.alias" should be equal to "/test-page-3"
    And the JSON node "data.attributes.path.url" should be equal to "/test-page-3"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 1"
    And the JSON node "data.attributes.field_test_link.url" should be equal to "http://site1.test/test-page-1"
    And the JSON node "data.attributes.body" should exist
    And the JSON node "data.attributes.body.processed" should contain "href="http://site1.test/test-page-1""
    And the JSON node "included[0]" should exist
    And the JSON node "included[0].attributes.path" should exist
    And the JSON node "included[0].attributes.path.url" should be equal to "http://site1.test/test-page-1"
