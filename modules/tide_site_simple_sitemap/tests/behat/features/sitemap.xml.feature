@tide
Feature: XML Sitemap per site

  @api
  Scenario: Different sites have different sitemap.xml
    Given vocabulary "sites" with name "Sites" exists
    And sites terms:
      | name        | parent | tid   | field_site_domains |
      | Test Site 1 | 0      | 10001 | site1.test         |
      | Test Site 2 | 0      | 10002 | site2.test         |

    Given test content:
      | title                         | body | moderation_state | path                    | field_node_primary_site | field_node_site          |
      | [TEST] Article with no site   | test | published        | /test-article-no-site   |                         |                          |
      | [TEST] Article with one site  | test | published        | /test-article-one-site  | Test Site 1             | Test Site 1              |
      | [TEST] Article with two sites | test | published        | /test-article-two-sites | Test Site 2             | Test Site 2, Test Site 1 |

    Given I run drush "simple_sitemap-generate --uri=http://content-vicgovau.docker.amazee.io"

    When I go to "sitemap.xml"
    Then the response status code should be 200
    And the response should contain "http://content-vicgovau.docker.amazee.io/"
    And the response should not contain "https://site1.test/"
    And the response should not contain "https://site2.test/"

    When I go to "sitemap.xml?site=10001"
    Then the response status code should be 200
    And the response should contain "https://site1.test/"
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"

    When I go to "sitemaps?site=10001"
    Then the response status code should be 200
    And the response should contain "https://site1.test/"
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"

    When I go to "sitemaps/1/sitemap.xml?site=10001"
    Then the response status code should be 200
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"
    And the response should not contain "https://site1.test/test-article-no-site"
    And the response should contain "https://site1.test/test-article-one-site"
    And the response should contain "https://site1.test/test-article-two-sites"

    When I go to "sitemap.xml?site=10002"
    Then the response status code should be 200
    And the response should contain "https://site2.test/"
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"

    When I go to "sitemaps?site=10002"
    Then the response status code should be 200
    And the response should contain "https://site2.test/"
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"

    When I go to "sitemaps/1/sitemap.xml?site=10002"
    Then the response status code should be 200
    And the response should not contain "http://content-vicgovau.docker.amazee.io/"
    And the response should not contain "https://site2.test/test-article-no-site"
    And the response should not contain "https://site2.test/test-article-one-site"
    And the response should contain "https://site2.test/test-article-two-sites"
