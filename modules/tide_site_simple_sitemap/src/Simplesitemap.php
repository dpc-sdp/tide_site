<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\SitemapGeneratorBase;
use Drupal\simple_sitemap\Simplesitemap as DefaultSimplesitemap;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
  protected function fetchSitemapChunk($id) {
    $site_id = $this->request->query->getInt('site');
    $page = $this->request->query->getInt('page');
    // If no site_id provided, returns its parent.
    if (empty($site_id)) {
      return parent::fetchSitemapChunk($id);
    }
    // If the page number is provided, return the chunk.
    if (!empty($page)) {
      $result = $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE delta = :delta and site_id = :site_id',
        [':delta' => $page, ':site_id' => $site_id])->fetchObject();
      return $this->validateResult($result);
    }
    // Returns the site-based pagination xml if it exists.
    if ($this->hasSiteMapChunks()) {
      $result = $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE delta = :delta and site_id = :site_id',
        [':delta' => SitemapGeneratorBase::INDEX_DELTA, ':site_id' => $site_id])
        ->fetchObject();
      return $this->validateResult($result);
    }
    // Returns the site-based xml if it exists.
    $result = $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE site_id = :site_id',
      [':site_id' => $site_id])->fetchObject();
    return $this->validateResult($result);
  }

  /**
   * Checks if tide_simple_site_map has chunks.
   */
  protected function hasSiteMapChunks() {
    $result = $this->db->select('simple_sitemap_site', 's')
      ->fields('s', ['delta'])
      ->condition('s.status', 1)
      ->condition('s.delta', SitemapGeneratorBase::INDEX_DELTA)
      ->execute()
      ->fetchAll();
    return !empty($result);
  }

  /**
   * Checks if the result is valid.
   */
  protected function validateResult($result) {
    if (empty($result)) {
      throw new NotFoundHttpException();
    }
    return $result;
  }

}
