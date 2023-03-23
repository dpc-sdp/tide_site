<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\Handler;

use Drupal\taxonomy\Entity\Term;

/**
 * Helper methods for Tide sitemap plugins.
 */
trait TideSitemapHandlerTrait {

  /**
   * Gets site id from the plugin.
   */
  protected function getSiteIdFromSitemapId() {
    $sites = $this->getAllSites();
    $ex = explode('-', $this->sitemap->id());
    $site_id = end($ex);
    if (is_numeric($site_id) && array_key_exists($site_id, $sites)) {
      return $site_id;
    }
    return NULL;
  }

  /**
   * Gets frontend url.
   */
  protected function getFrontendUrl($be_url): ?string {
    $parsed_url = parse_url($be_url);
    $site_id = $this->getSiteIdFromSitemapId();
    if ($this->siteHelper->hasSitePrefix($parsed_url['path']) && $site_id) {
      $site_term = Term::load($site_id);
      $site_base_url = $this->siteHelper->getSiteBaseUrl($site_term);
      $url = $this->siteHelper->overrideUrlStringWithSite($be_url, $site_term);
      return $this->aliasStorageHelper->getPathAliasWithoutSitePrefix(
        ['alias' => $url],
        $site_base_url
      );
    }
    return NULL;
  }

  /**
   * Gets all sites.
   */
  protected function getAllSites(): array {
    return $this->siteHelper->getAllSites();
  }

  /**
   * Return site base url.
   */
  protected function getSiteBaseUrl(): string {
    $site_base_url = '';
    $site_id = $this->getSiteIdFromSitemapId();
    if ($site_id) {
      $site_term = Term::load($site_id);
      $site_base_url = $this->siteHelper->getSiteBaseUrl($site_term);
    }
    return $site_base_url;
  }

}
