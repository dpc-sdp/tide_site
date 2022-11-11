<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\simple_sitemap\SimplesitemapManager as DefaultSimplesitemapManager;

/**
 * Class simple sitemap.
 *
 * @package Drupal\tide_site_simple_sitemap
 */
class SimplesitemapManager extends DefaultSimplesitemapManager {

  /**
   * Limit removal by specific variants.
   *
   * {@inheritdoc}
   */
  public function removeSitemap($variant_names = NULL) {
    parent::removeSitemap($variant_names);
    if (NULL === $variant_names || !empty((array) $variant_names)) {
      $saved_variants = $this->getSitemapVariants();
      $remove_variants = NULL === $variant_names
        ? $saved_variants
        : array_intersect_key($saved_variants, array_flip((array) $variant_names));

      if (!empty($remove_variants)) {
        foreach ($remove_variants as $variant_name => $variant_definition) {
          $this->db->delete('simple_sitemap_site')
            ->condition('type', $variant_name)
            ->execute();
        }
      }
    }

    return $this;
  }

}
