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
  public function getIndexContent(): string {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    $this->addXslUrl();
    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    $this->moduleHandler->alter('simple_sitemap_index_attributes', $attributes, $this->sitemap);
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }
    $site_base_url = '';
    $helper = \Drupal::service('tide_site.helper');
    $site_id = $this->getSiteIdFromPluginId();
    if ($site_id) {
      $site_term = Term::load($site_id);
      $site_base_url = $helper->getSiteBaseUrl($site_term);
    }
    // Add sitemap chunk locations to document.
    for ($delta = 1; $delta <= $this->sitemap->fromUnpublished()->getChunkCount(); $delta++) {
      $this->writer->startElement('sitemap');
      if (empty($site_base_url)) {
        $this->writer->writeElement('loc', $this->sitemap->toUrl('canonical', ['delta' => $delta])->toString());
      }
      else {
        $this->writer->writeElement('loc', $site_base_url . '/sitemap.xml?page=' . $delta);
      }
      $this->writer->writeElement('lastmod', date('c', $this->sitemap->fromUnpublished()->getCreated()));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

  /**
   * Gets site id from the plugin.
   */
  protected function getSiteIdFromPluginId() {
    $sites = \Drupal::service('tide_site.helper')->getAllSites();
    $ex = explode('-', $this->sitemap->id());
    $site_id = end($ex);
    if (is_numeric($site_id) && array_key_exists($site_id, $sites)) {
      return $site_id;
    }
    return NULL;
  }

}
