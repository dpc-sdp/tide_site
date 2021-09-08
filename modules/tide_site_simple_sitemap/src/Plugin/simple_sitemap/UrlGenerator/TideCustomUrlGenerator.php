<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Annotation\UrlGenerator;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\CustomUrlGenerator;

/**
 * Class TideCustomUrlGenerator
 * @package Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator
 *
 * @UrlGenerator(
 *   id = "tide_custom",
 *   label = @Translation("Tide Custom URL generator"),
 *   description = @Translation("Generates URLs set in admin/config/search/simplesitemap/custom."),
 * )
 *
 */
class TideCustomUrlGenerator extends CustomUrlGenerator {

}
