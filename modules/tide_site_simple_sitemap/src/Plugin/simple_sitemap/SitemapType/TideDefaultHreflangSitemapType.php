<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\SitemapType;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapType\DefaultHreflangSitemapType;

/**
 * Class DefaultHreflangSitemapType
 *
 * @SitemapType(
 *   id = "tide_default_hreflang",
 *   label = @Translation("Tide Default hreflang"),
 *   description = @Translation("The default hreflang sitemap type."),
 *   sitemapGenerator = "tide_default",
 *   urlGenerators = {
 *     "tide_custom",
 *     "tide_entity",
 *   },
 * )
 */
class TideDefaultHreflangSitemapType extends DefaultHreflangSitemapType {

}
