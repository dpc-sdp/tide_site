<?php

namespace Drupal\tide_site_simple_sitemap;

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
   * {@inheridoc}
   */
  protected function fetchSitemapChunk($id) {
    $site_id = $this->request->get('site');
    $page = $this->request->get('page');
    try {
      if (!empty($site_id) && !empty($page)) {
        return $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE delta = :delta and site_id = :site_id',
          [':delta' => $page, ':site_id' => $site_id])->fetchObject();
      }
      elseif (empty($site_id)) {
        return parent::fetchSitemapChunk($id);
      }
      else {
        if ($this->hasSiteMapChunks()) {
          return $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE delta = :delta and site_id = :site_id',
            [':delta' => 0, ':site_id' => $site_id])->fetchObject();
        }
        return $this->db->query('SELECT * FROM {simple_sitemap_site} WHERE site_id = :site_id',
          [':site_id' => $site_id])->fetchObject();
      }
    } catch (\Exception $exception) {
      throw new NotFoundHttpException();
    }
  }

  protected function hasSiteMapChunks() {
    $result = \Drupal::database()->select('simple_sitemap_site', 's')
      ->fields('s', ['delta'])
      ->condition('s.status', 1)
      ->condition('s.delta', '0', '=')
      ->execute()
      ->fetchAll();
    return !empty($result);
  }

}
