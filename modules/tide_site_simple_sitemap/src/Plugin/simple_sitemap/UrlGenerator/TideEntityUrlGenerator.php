<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;

/**
 * Class entity url generator.
 *
 * @UrlGenerator(
 *   id = "tide_entity",
 *   label = @Translation("Tide Entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle
 *   overrides."),
 * )
 */
class TideEntityUrlGenerator extends EntityUrlGenerator {

}
