<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\simple_sitemap\Simplesitemap as DefaultSimplesitemap;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Simplesitemap.
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
   * {@inheritdoc}
   */
  protected function fetchSitemapChunkInfo() {
    $site_id = $this->request->get('site');
    if (empty($site_id)) {
      return parent::fetchSitemapChunkInfo();
    }

    return $this->db
      ->query('SELECT id, sitemap_created FROM {simple_sitemap_site} WHERE site_id = :site_id', [':site_id' => $site_id])
      ->fetchAllAssoc('id');
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchSitemapChunk($id) {
    $site_id = $this->request->get('site');
    if (empty($site_id)) {
      return parent::fetchSitemapChunk($id);
    }

    return $this->db->query(
      'SELECT * FROM {simple_sitemap_site} WHERE id = :id AND site_id = :site_id',
      [':id' => $id, ':site_id' => $site_id]
    )->fetchObject();
  }

}
