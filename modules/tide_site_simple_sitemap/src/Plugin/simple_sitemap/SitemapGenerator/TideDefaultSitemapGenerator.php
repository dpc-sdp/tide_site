<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;

/**
 * Class default sitemap generator.
 *
 * @SitemapGenerator(
 *   id = "tide_default",
 *   label = @Translation("Tide Default sitemap generator"),
 *   description = @Translation("Generates a standard conform hreflang sitemap
 *   of your content."),
 * )
 */
class TideDefaultSitemapGenerator extends DefaultSitemapGenerator {

  /**
   * {@inheritdoc}
   */
  public function generate(array $links) {
    parent::generate($links);
    $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap_site}')
      ->fetchField();
    $highest_delta = $this->db->query('SELECT MAX(delta) FROM {simple_sitemap_site} WHERE type = :type AND status = :status', [
      ':type' => $this->sitemapVariant,
      ':status' => 0,
    ])
      ->fetchField();
    $this->db->truncate('simple_sitemap_site')->execute();
    $sites = \Drupal::service('tide_site.helper')->getAllSites();
    if (!empty($sites)) {
      // Prepare to copy/split sitemap links to all sites.
      $links_per_site = [];
      /** @var \Drupal\taxonomy\TermInterface $site */
      foreach ($sites as $site_id => $site) {
        $links_per_site[$site_id] = [];
      }

      foreach ($links as $link) {
        $restricted_by_site = FALSE;
        $meta = &$link['meta'];
        // Check if the link has an entity which is restricted by site.
        if (!empty($meta['entity_info']['entity_type'])) {
          $entity_type = $meta['entity_info']['entity_type'];
          if (\Drupal::service('tide_site.helper')
            ->isRestrictedEntityType($entity_type)) {
            $restricted_by_site = TRUE;
            $entity_id = $meta['entity_info']['id'];
            $entity = \Drupal::entityTypeManager()
              ->getStorage($entity_type)
              ->load($entity_id);
            if ($entity) {
              // Get all Site Base URLs of the entity.
              $site_base_urls = \Drupal::service('tide_site.helper')
                ->getEntitySiteBaseUrls($entity);
              if (!empty($site_base_urls)) {
                // This entity should be only in the sitemap of its sites.
                foreach ($site_base_urls as $site_id => $site_base_url) {
                  $url = $link['url'];
                  // Override the link URL with the site Base URL.
                  $url = \Drupal::service('tide_site.helper')
                    ->overrideUrlStringWithSiteBaseUrl($url, $site_base_url);
                  // Remove Site prefix.
                  $url = \Drupal::service('tide_site.alias_storage_helper')
                    ->getPathAliasWithoutSitePrefix(['alias' => $url], $site_base_url);
                  // Copy to the Site sitemap.
                  $entity_link = $link;
                  $entity_link['url'] = $url;
                  $links_per_site[$site_id][] = $entity_link;
                }
              }
            }
          }
        }

        // This link does not have an entity,
        // or the entity is not restricted by site.
        if (!$restricted_by_site) {
          // Copy the link to the sitemap of all sites.
          foreach ($sites as $site_id => $site) {
            // Override the link URL with Site base URL.
            $url = \Drupal::service('tide_site.helper')
              ->overrideUrlStringWithSite($link['url'], $site, 'https');
            // Copy to the Site sitemap.
            $site_link = $link;
            $site_link['url'] = $url;
            $links_per_site[$site_id][] = $site_link;
          }
        }
      }

      // Now we have the sitemap of all sites.
      foreach ($links_per_site as $site_id => $site_links) {
        if (count($site_links)) {
          // Write to our own table.
          $values = [
            'id' => NULL === $highest_id ? 0 : $highest_id + 1,
            'site_id' => $site_id,
            'delta' => NULL === $highest_delta ? self::FIRST_CHUNK_DELTA : $highest_delta + 1,
            'type' => $this->sitemapVariant,
            'sitemap_string' => $this->getXml($site_links),
            'sitemap_created' => $this->time->getRequestTime(),
            'status' => 0,
            'link_count' => count($links),
          ];
          $this->db->insert('simple_sitemap_site')->fields($values)->execute();
        }
      }
    }
  }

  /**
   * Generate Index.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function generateIndex() {
    if (!empty($chunk_info = $this->getChunkInfo()) && count($chunk_info) > 1) {
      $index_xml = $this->getIndexXml($chunk_info);
      $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap_site}')
        ->fetchField();
      $this->db->merge('simple_sitemap_site')
        ->keys([
          'delta' => self::INDEX_DELTA,
          'type' => $this->sitemapVariant,
          'status' => 0,
        ])
        ->insertFields([
          'id' => NULL === $highest_id ? 0 : $highest_id + 1,
          'delta' => self::INDEX_DELTA,
          'type' => $this->sitemapVariant,
          'sitemap_string' => $index_xml,
          'sitemap_created' => $this->time->getRequestTime(),
          'status' => 0,
        ])
        ->updateFields([
          'sitemap_string' => $index_xml,
          'sitemap_created' => $this->time->getRequestTime(),
        ])
        ->execute();
    }

    return $this;
  }

  /**
   * Get chunk info.
   */
  protected function getChunkInfo() {
    return $this->db->select('simple_sitemap_site', 's')
      ->fields('s', ['delta', 'sitemap_created', 'type'])
      ->condition('s.type', $this->sitemapVariant)
      ->condition('s.delta', self::INDEX_DELTA, '<>')
      ->condition('s.status', 0)
      ->execute()
      ->fetchAllAssoc('delta');
  }

  /**
   * Remove function for sitemap generator.
   */
  public function remove($mode = 'all') {
    parent::purgeSitemapVariants($this->sitemapVariant, $mode);
    self::purgeSitemapVariants($this->sitemapVariant, $mode);

    return $this;
  }

  /**
   * Purge sitemap variants.
   */
  public static function purgeSitemapVariants($variants = NULL, $mode = 'all') {
    if (NULL === $variants || !empty((array) $variants)) {
      $delete_query = \Drupal::database()->delete('simple_sitemap_site');

      switch ($mode) {
        case 'published':
          $delete_query->condition('status', 1);
          break;

        case 'unpublished':
          $delete_query->condition('status', 0);
          break;

        case 'all':
          break;

        default:

          // @todo throw error.
      }

      if (NULL !== $variants) {
        $delete_query->condition('type', (array) $variants, 'IN');
      }

      $delete_query->execute();
    }
  }

}