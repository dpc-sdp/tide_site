<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
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
    $highest_delta = $this->db->query('SELECT MAX(delta) FROM {simple_sitemap} WHERE type = :type AND status = :status', [
      ':type' => $this->sitemapVariant,
      ':status' => 0,
    ])
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
            'id' => $highest_id ?? 0,
            'site_id' => $site_id,
            'delta' => $highest_delta ?? self::FIRST_CHUNK_DELTA,
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
   * Publish new sitemaps.
   *
   * {@inheridoc}
   */
  public function publish() {
    $unpublished_chunk = $this->db->query('SELECT MAX(id) FROM {simple_sitemap_site} WHERE type = :type AND status = :status', [
      ':type' => $this->sitemapVariant,
      ':status' => 0,
    ])->fetchField();

    // Only allow publishing a sitemap variant if there is an unpublished
    // sitemap variant, as publishing involves deleting the currently published
    // variant.
    if (FALSE !== $unpublished_chunk) {
      $this->tideSiteMapRemove('published');
      $this->db->query('UPDATE {simple_sitemap_site} SET status = :status WHERE type = :type', [
        ':type' => $this->sitemapVariant,
        ':status' => 1,
      ]);
    }
    parent::publish();
    return $this;
  }

  /**
   * This is mostly for fixing the function signature issue.
   */
  public function tideSiteMapRemove($mode = 'all') {
    self::purgeSitemapVariants($this->sitemapVariant, $mode);
    return $this;
  }

  /**
   * Remove previous sitemaps once new ones get published.
   *
   * {@inheridoc}
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
          // @todo throw error
      }

      if (NULL !== $variants) {
        $delete_query->condition('type', (array) $variants, 'IN');
      }

      $delete_query->execute();
    }
  }

  /**
   * Generate an index sitemap for site-based sitemap.xml.
   *
   * {@inheridoc}
   */
  public function generateIndex() {
    parent::generateIndex();
    if (!empty($chunk_info = $this->getChunkInfo()) && count($chunk_info) > 1) {
      $highest_id = $this->db->query('SELECT MAX(id) FROM {simple_sitemap}')
        ->fetchField();
      $sites = \Drupal::service('tide_site.helper')->getAllSites();
      foreach ($sites as $term_id => $item) {
        $this->db->merge('simple_sitemap_site')
          ->keys([
            'delta' => self::INDEX_DELTA,
            'site_id' => $term_id,
            'type' => $this->sitemapVariant,
            'status' => 0,
          ])
          ->insertFields([
            'id' => $highest_id ?? 0,
            'site_id' => $term_id,
            'delta' => self::INDEX_DELTA,
            'type' => $this->sitemapVariant,
            'sitemap_string' => $this->getSiteBasedIndexXml($this->getChunkInfo(), $item),
            'sitemap_created' => $this->time->getRequestTime(),
            'status' => 0,
          ])
          ->updateFields([
            'sitemap_string' => $this->getSiteBasedIndexXml($this->getChunkInfo(), $item),
            'sitemap_created' => $this->time->getRequestTime(),
          ])
          ->execute();
      }
    }

    return $this;
  }

  /**
   * Gets a site-based sitemap.xml.
   *
   * {@inheridoc}
   */
  private function getSiteBasedIndexXml(array $chunk_info, Term $site) {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    // Add the XML stylesheet to document if enabled.
    if ($this->settings['xsl']) {
      $this->writer->writeXsl();
    }

    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    $sitemap_variant = $this->sitemapVariant;
    $this->moduleHandler->alter('simple_sitemap_index_attributes', $attributes, $sitemap_variant);
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }
    $site_url = \Drupal::service('tide_site.helper')
      ->getSiteBaseUrl($site);
    if (!$this->isDefaultVariant()) {
      $site_url .= '/' . $this->sitemapVariant;
    }
    // Add sitemap chunk locations to document.
    foreach ($chunk_info as $chunk_data) {
      $this->writer->startElement('sitemap');
      $this->writer->writeElement('loc', Url::fromRoute(
        'simple_sitemap.sitemap_default',
        ['page' => $chunk_data->delta],
        [
          'absolute' => TRUE,
          'base_url' => $site_url,
          'language' => $this->languageManager->getLanguage(LanguageInterface::LANGCODE_NOT_APPLICABLE),
        ])->toString());
      $this->writer->writeElement('lastmod', date('c', $chunk_data->sitemap_created));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

}
