@tide @jsonapi
Feature: Route lookup

  @api @nosuggest
  Scenario: Request to route lookup API to find a route by existing alias
    And vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name            | parent      | tid   |
      | Test Site 1     | 0           | 10001 |
      | Test Section 11 | Test Site 1 | 10011 |
      | Test Site 2     | 0           | 10002 |

    Given test content:
      | title                         | body | moderation_state | path                    | field_node_primary_site | field_node_site              |
      | [TEST] Article with no site   | test | published        | /test-article-no-site   |                         |                              |
      | [TEST] Article with one site  | test | published        | /test-article-one-site  | Test Site 1             | Test Site 1, Test Section 11 |
      | [TEST] Article with two sites | test | published        | /test-article-two-sites | Test Site 2             | Test Site 2, Test Site 1     |

    And I am an anonymous user

    When I send a GET request to "api/v1/route?path=/test-article-no-site"
    Then the rest response status code should be 200
    And the JSON node "data" should exist
    And the JSON node "errors" should not exist
    And the response should be in JSON

    When I send a GET request to "api/v1/route?path=/test-article-one-site"
    Then the rest response status code should be 404
    And the response should be in JSON
    And the JSON node "data" should not exist
    And the JSON node "errors" should exist

    When I send a GET request to "api/v1/route?path=/test-article-one-site&site=10001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "data.section" should not contain "10001"
    And the JSON node "data.section" should contain "10011"
    And the JSON node "errors" should not exist

    When I send a GET request to "api/v1/route?path=/test-article-one-site&site=10011"
    Then the rest response status code should be 404
    And the response should be in JSON
    And the JSON node "data" should not exist
    And the JSON node "errors" should exist
    And the JSON array node "errors" should contain "Path not found." element

    When I send a GET request to "api/v1/route?path=/test-article-two-sites&site=10001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "data.section" should contain "10001"
    And the JSON node "errors" should not exist

    When I send a GET request to "api/v1/route?path=/test-article-two-sites&site=10002"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "data.section" should contain "10002"
    And the JSON node "errors" should not exist

  @api @nosuggest
  Scenario: Request to route lookup API to find the homepage of a site
    Given sites terms:
      | name        | parent | tid   | field_site_domains |
      | Test Site 3 | 0      | 10003 | test.site.local    |

    Given test content:
      | title           | moderation_state | field_node_primary_site | field_node_site | uuid                                 |
      | [TEST] Homepage | published        | Test Site 3             | Test Site 3     | 00000000-1111-2222-3333-0123456789ab |

    Given I am logged in as a user with the "administer taxonomy" permission
    When I visit "/taxonomy/term/10003/edit"
    Then I fill in "Homepage" with "[TEST] Homepage"
    And I press the Save button

    Given I am an anonymous user
    When I send a GET request to "api/v1/route?path=/&site=10003"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "data.entity_type" should be equal to "node"
    And the JSON node "data.bundle" should be equal to "test"
    And the JSON node "data.uuid" should contain "00000000-1111-2222-3333-0123456789ab"
    And the JSON node "data.section" should be equal to "10003"
    And the JSON node "errors" should not exist
