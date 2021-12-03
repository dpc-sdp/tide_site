<?php

namespace Drupal\Tests\tide_site_preview\Kernel;

use Drupal\Core\Url;
use Drupal\Tests\token\Kernel\KernelTestBase;

/**
 * Tests the TideSitePreviewHelper
 *
 * @group tide_site_preview
 */
class TideSitePreviewHelperTest extends KernelTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['tide_site_preview']);
    $this->tideSitePreviewHelper =\Drupal::service('tide_site_preview.helper');
  }

  /**
   * @covers ::getNodeFrontendUrl
   * @dataProvider urlDataProvider
   */
  public function testgetNodeFrontendUrl($value, $expected) {
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

}
