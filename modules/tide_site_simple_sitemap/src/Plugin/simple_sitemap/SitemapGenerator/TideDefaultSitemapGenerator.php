<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;
use Drupal\taxonomy\Entity\Term;

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
    $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')->fetchField();
    $highest_delta = $this->db->query('SELECT MAX(delta) FROM {simple_sitemap} WHERE type = :type AND status = :status', [':type' => $this->sitemapVariant, ':status' => 0])
      ->fetchField();

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
        if (!empty($site_links)) {
          // Write to our own table.
          $values = [
            'id' => NULL === $highest_id ? 0 : $highest_id,
            'site_id' => $site_id,
            'delta' => NULL === $highest_delta ? self::FIRST_CHUNK_DELTA : $highest_delta,
            'type' => $this->sitemapVariant,
            'sitemap_string' => $this->getXml($site_links),
            'sitemap_created' => $this->time->getRequestTime(),
            'status' => 0,
            'link_count' => count($site_links),
          ];
          $this->db->insert('simple_sitemap_site')->fields($values)->execute();
        }
      }
    }
  }

  /**
   * {@inheridoc}
   */
  public function publish() {
    $unpublished_chunk = $this->db->query('SELECT MAX(id) FROM {simple_sitemap_site} WHERE type = :type AND status = :status', [
      ':type' => $this->sitemapVariant, ':status' => 0
    ])->fetchField();

    // Only allow publishing a sitemap variant if there is an unpublished
    // sitemap variant, as publishing involves deleting the currently published
    // variant.
    if (FALSE !== $unpublished_chunk) {
      $this->TideSiteMapRemove('published');
      $this->db->query('UPDATE {simple_sitemap_site} SET status = :status WHERE type = :type', [':type' => $this->sitemapVariant, ':status' => 1]);
    }
    parent::publish();
    return $this;
  }

  /**
   * @param string $mode
   * @return $this
   */
  public function TideSiteMapRemove($mode = 'all') {
    self::purgeSitemapVariants($this->sitemapVariant, $mode);

    return $this;
  }

  /**
   * {@inheridoc}
   */
  public static function purgeSitemapVariants($variants = NULL, $mode = 'all') {
    if (NULL === $variants || !empty((array) $variants)) {
      $delete_query = \Drupal::database()->delete('simple_sitemap_site');

      switch($mode) {
        case 'published':
          $delete_query->condition('status', 1);
          break;

        case 'unpublished':
          $delete_query->condition('status', 0);
          break;

        case 'all':
          break;

        default:
          //todo: throw error
      }

      if (NULL !== $variants) {
        $delete_query->condition('type', (array) $variants, 'IN');
      }

      $delete_query->execute();
    }
  }

  public function generateIndex() {
    parent::generateIndex();
    if (!empty($chunk_info = $this->getChunkInfo()) && count($chunk_info) > 1) {
      $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')
        ->fetchField();
      $infos = \Drupal::database()->select('simple_sitemap_site', 's')
        ->fields('s', ['delta', 'site_id'])
        ->condition('s.status', 0)
        ->execute()
        ->fetchAll();
      $pages = [];
      foreach ($infos as $info) {
        $pages[$info->site_id][] = $info->delta;
      }
      foreach ($pages as $term_id => $page) {
        $xmlstr = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!--Generated by the Simple XML Sitemap Drupal module: https://drupal.org/project/simple_sitemap.-->
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
</urlset>
XML;
        $new_xml = simplexml_load_string($xmlstr);
        $term = Term::load($term_id);
        $site_url = \Drupal::service('tide_site.helper')->getSiteBaseUrl($term);
        foreach ($page as $item) {
          $url = $new_xml->addChild('url', '');
          $url->addChild('loc', $site_url . '/sitemap.xml?page=' . $item);
          $url->addChild('lastmod', date('c', $this->time->getRequestTime()));
        }
        $this->db->merge('simple_sitemap_site')
          ->keys([
            'delta' => self::INDEX_DELTA,
            'site_id' => $term_id,
            'type' => $this->sitemapVariant,
            'status' => 0,
          ])
          ->insertFields([
            'id' => NULL === $highest_id ? 0 : $highest_id,
            'site_id' => $term_id,
            'delta' => self::INDEX_DELTA,
            'type' => $this->sitemapVariant,
            'sitemap_string' => $new_xml->asXML(),
            'sitemap_created' => $this->time->getRequestTime(),
            'status' => 0,
          ])
          ->updateFields([
            'sitemap_string' => $new_xml->asXML(),
            'sitemap_created' => $this->time->getRequestTime(),
          ])
          ->execute();
      }
    }

    return $this;
  }

}
