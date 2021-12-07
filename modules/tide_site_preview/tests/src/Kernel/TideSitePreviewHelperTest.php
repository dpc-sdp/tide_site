<?php

namespace Drupal\Tests\tide_site_preview\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests the TideSitePreviewHelper.
 *
 * @coversDefaultClass \Drupal\tide_site_preview\TideSitePreviewHelper
 * @group tide_site_preview
 */
class TideSitePreviewHelperTest extends EntityKernelTestBase {

  /**
   * The tide_site_preview.helper service.
   *
   * @var \Drupal\tide_site_preview\TideSitePreviewHelper
   */
  protected $tideSitePreviewHelper;

  /**
   * The modules to load to run the test.
   *
   * @var array
   */
  public static $modules = [
    'tide_site',
    'tide_site_preview',
    'node',
    'taxonomy',
    'test_tide_site_preview',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['tide_site_preview']);
    $this->installConfig(['test_tide_site_preview']);
    $this->tideSitePreviewHelper = \Drupal::service('tide_site_preview.helper');
  }

  /**
   * @covers ::getNodeFrontendUrl
   * @dataProvider urlDataProvider
   */
  public function testGetNodeFrontendUrl($value, $expected) {
    $url = Url::fromUri($value);
    $url = $this->tideSitePreviewHelper->getNodeFrontendUrl($url);
    $this->assertEquals($expected, $url->toString());
  }

  /**
   * Data provider of test dates.
   *
   * @return array
   *   Array of values.
   */
  public function urlDataProvider() {
    return [
      [
        'value' => 'https://www.vic.gov.au/site-1/hello_world',
        'expected' => 'https://www.vic.gov.au/hello_world',
      ],
      [
        'value' => 'https://site-1/hello_world',
        'expected' => 'https://hello_world',
      ],
      [
        'value' => 'https://site-1/',
        'expected' => 'https://site-1',
      ],
      [
        'value' => 'https://site-1',
        'expected' => 'https://site-1',
      ],
    ];
  }

  /**
   * @covers ::buildFrontendPreviewLink
   * @dataProvider buildFrontendPreviewLinkDataProvider
   */
  public function testBuildFrontendPreviewLink($node_values, $url, $term_values, $config, $expected) {
    $node = Node::create($node_values);
    $node->save();
    $url = Url::fromUserInput($url);
    $term = Term::create($term_values);
    $term->save();

    $result = $this->tideSitePreviewHelper->buildFrontendPreviewLink($node, $url, $term, NULL, $config);
    $url = $result['url']->toString();
    $this->assertEquals($expected, $url);
  }

  /**
   * Data provider of test dates.
   *
   * @return array
   *   Array of values.
   */
  public function buildFrontendPreviewLinkDataProvider() {
    return [
      [
        [
          'type' => 'test',
          'title' => 'test_1',
        ],
        '/site-1/hello_world',
        [
          'vid' => 'sites',
          'name' => 'vicgovau',
          'field_site_domains' => 'www.vic.gov.au',
        ],
        [
          'id' => 'tide_site_preview_links_block',
          'label' => 'Click the links below to preview this revision on frontend sites',
          'provider' => 'tide_site_preview',
          'label_display' => 'visible',
          'open_new_window' => 1,
        ],
        'https://www.vic.gov.au/hello_world',
      ],
      [
        [
          'type' => 'test',
          'title' => 'test_2',
        ],
        '/site-2/site-4/hello_world',
        [
          'vid' => 'sites',
          'name' => 'vicgovau',
          'field_site_domains' => 'www.vic.gov.au',
        ],
        [
          'id' => 'tide_site_preview_links_block',
          'label' => 'Click the links below to preview this revision on frontend sites',
          'provider' => 'tide_site_preview',
          'label_display' => 'visible',
          'open_new_window' => 1,
        ],
        'https://www.vic.gov.au/site-4/hello_world',
      ],
      [
        [
          'type' => 'test',
          'title' => 'test_3',
        ],
        '/site-3/site-6/site-7/hello_world',
        [
          'vid' => 'sites',
          'name' => 'vicgovau',
          'field_site_domains' => 'www.vic.gov.au',
        ],
        [
          'id' => 'tide_site_preview_links_block',
          'label' => 'Click the links below to preview this revision on frontend sites',
          'provider' => 'tide_site_preview',
          'label_display' => 'visible',
          'open_new_window' => 1,
        ],
        'https://www.vic.gov.au/site-6/site-7/hello_world',
      ],
      [
        [
          'type' => 'test',
          'title' => 'test_4',
        ],
        '/site-4/hello_world',
        [
          'vid' => 'sites',
          'name' => 'vicgovau',
          'field_site_domains' => 'www.vic.gov.au',
        ],
        [
          'id' => 'tide_site_preview_links_block',
          'label' => 'Click the links below to preview this revision on frontend sites',
          'provider' => 'tide_site_preview',
          'label_display' => 'visible',
          'open_new_window' => 1,
        ],
        'https://www.vic.gov.au/hello_world',
      ],
    ];
  }

}
