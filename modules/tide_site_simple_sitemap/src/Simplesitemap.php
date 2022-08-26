<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;
use Drupal\simple_sitemap\Simplesitemap as DefaultSimplesitemap;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class simple sitemap.
 *
 * @package Drupal\tide_site_simple_sitemap
 */
class Simplesitemap extends DefaultSimplesitemap {

  /**
   * The current Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Set the current request.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function setRequest(RequestStack $request_stack) {
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * Fetches a single sitemap chunk by site and page.
   *
   * {@inheridoc}
   */
  protected function fetchSitemapChunk($id, $site_id = NULL) {
    if (empty($site_id)) {
      return parent::fetchSitemapChunk($id);
    }
    return $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE id = :id and site_id = :site_id',
      [':id' => $id, ':site_id' => $site_id])->fetchObject();
  }

  /**
   * Returns a sitemap variant, its index, or its requested chunk.
   *
   * {@inheritdoc}
   */
  public function getSitemap($delta = NULL) {
    $site_id = $this->request->query->getInt('site');
    if (empty($site_id)) {
      parent::getSitemap($delta);
    }
    $chunk_info = $this->fetchSitemapVariantInfo();
    if (empty($delta) || !isset($chunk_info[$delta])) {

      if (isset($chunk_info[SitemapGeneratorBase::INDEX_DELTA])) {
        // Return sitemap index if one exists.
        return $this->fetchSitemapChunk($chunk_info[SitemapGeneratorBase::INDEX_DELTA]->id, $site_id)
          ->sitemap_string;
      }

      // Return sitemap chunk if there is only one chunk.
      return isset($chunk_info[SitemapGeneratorBase::FIRST_CHUNK_DELTA])
        ? $this->fetchSitemapChunk($chunk_info[SitemapGeneratorBase::FIRST_CHUNK_DELTA]->id, $site_id)
          ->sitemap_string
        : FALSE;
    }

    // Return specific sitemap chunk.
    return $this->fetchSitemapChunk($chunk_info[$delta]->id, $site_id)->sitemap_string;
  }

}
