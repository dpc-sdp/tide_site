@tide
Feature: Page

  @api
  Scenario: Request to "test" collection endpoint
    Given vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name        | parent | tid   |
      | Test Site 1 | 0      | 10001 |

    Given I am an anonymous user

    When I send a GET request to "api/v1/node/test?site=10001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "jsonapi.version" should be equal to "1.0"
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "meta.count" should exist
    And the JSON node "data" should exist

  @api
  Scenario: Request to "test" individual/collection endpoint with results
    Given vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name            | parent      | tid   |
      | Test Site 1     | 0           | 10001 |
      | Test Section 11 | Test Site 1 | 10011 |
      | Test Site 2     | 0           | 10002 |
      | Test Site 3     | 0           | 10003 |

    Given test content:
      | title               | path               | moderation_state | uuid                                | field_node_site              |
      | [TEST] Page title   | /test-page-alias   | published        | 99999999-aaaa-bbbb-ccc-000000000000 | Test Site 1, Test Section 11 |
      | [TEST] Page title 2 | /test-page-alias-2 | published        | 99999999-aaaa-bbbb-ddd-000000000000 | Test Site 1                  |
      | [TEST] Page title 3 | /test-page-alias-3 | published        | 99999999-aaaa-bbbb-eee-000000000000 | Test Site 1, Test Site 3     |

    Given I am an anonymous user

    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000"
    Then the rest response status code should be 400

    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000?site=10000"
    Then the rest response status code should be 404

    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000?site=10001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000000"

    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000?site=10011"
    Then the rest response status code should be 404

    When I send a GET request to "api/v1/node/test?sort=-created&site=10001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "jsonapi.version" should be equal to "1.0"
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "meta.count" should exist
    And the JSON node "meta.count" should be equal to "3"
    And the JSON node "data" should exist
    And the JSON node "data[0].type" should be equal to "node--test"
    And the JSON node "data[0].id" should exist
    And the JSON node "data[0].attributes.title" should contain "[TEST] Page title"

    When I send a GET request to "api/v1/node/test?sort=-created&site=10011"
    Then the rest response status code should be 400

    When I send a GET request to "api/v1/node/test?sort=-created&site=10002"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "meta.count" should exist
    And the JSON node "meta.count" should be equal to "0"

    When I send a GET request to "api/v1/node/test?sort=-created&site=10003"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "meta.count" should exist
    And the JSON node "meta.count" should be equal to "1"
