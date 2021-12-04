<?php

namespace Drupal\tide_site_preview;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tide_site\TideSiteHelper;
use Drupal\Core\Url;

/**
 * Helper class for Tide Site Preview.
 */
class TideSitePreviewHelper {

  /**
   * Tide Site Helper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   *   The Tide Site Helper.
   */
  private $siteHelper;

  /**
   * Construct a new Tide Site Preview Helper.
   *
   * @param \Drupal\tide_site\TideSiteHelper $site_helper
   *   The Tide Site Helper.
   */
  public function __construct(TideSiteHelper $site_helper) {
    $this->siteHelper = $site_helper;
  }

  /**
   * Build the frontend preview link array of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param \Drupal\taxonomy\TermInterface $site
   *   The site of the preview link.
   * @param \Drupal\taxonomy\TermInterface|null $section
   *   The section of the preview link.
   * @param \array $configuration
   *   Plugin configuration if available.
   *
   * @return array
   *   The preview link array with following keys:
   *   * #site: The site object.
   *   * #section: The section object.
   *   * name: The site/section name.
   *   * url: The absolute URL of the preview link.
   */
  public function buildFrontendPreviewLink(NodeInterface $node, Url $url, TermInterface $site, TermInterface $section = NULL, array $configuration = []) : array {
    $url_options = [
      'attributes' => !(empty($configuration['open_new_window'])) ? ['target' => '_blank'] : [],
    ];
    if ($section) {
      $url_options['query']['section'] = $section->id();
    }

    $preview_link = [
      '#site' => $site,
      '#section' => $section,
      'name' => $site->getName(),
    ];
    if ($section && $section->id() !== $site->id()) {
      $preview_link['name'] = $site->getName() . ' - ' . $section->getName();
    }
    $site_base_url = $this->siteHelper->getSiteBaseUrl($site);
    if ($node->isPublished() && $node->isDefaultRevision()) {
      unset($url_options['query']['section']);
      $preview_link['url'] = $this->getNodeFrontendUrl($url, $site_base_url, $url_options);
    }
    else {
      $revision_id = $node->getLoadedRevisionId();
      $is_latest_revision = $node->isLatestRevision();
      $content_type = $node->bundle();
      $url = !empty($site_base_url) ? ($site_base_url . '/preview/' . $content_type . '/' . $node->uuid() . '/' . ($is_latest_revision ? 'latest' : $revision_id)) : '';
      $preview_link['url'] = (!empty($url) && !empty($url_options)) ? Url::fromUri($url, $url_options) : '';
    }

    return $preview_link;
  }

  /**
   * Get the frontend URL of a node.
   *
   * @param \Drupal\Core\Url $url
   *   The node.
   * @param string $site_base_url
   *   The base URL of the frontend.
   * @param array $url_options
   *   The extra options.
   * @return \Drupal\Core\Url|string
   *   The Url.
   */
  public function getNodeFrontendUrl(Url $url, string $site_base_url = '', array $url_options = []) {
    try {
      $path = $url->toString();
      $path = rtrim($path, '/');
      $clean_url = preg_replace('/\/site\-(\d+)\//', '/', $path,1);
      if ((strpos($clean_url, '/') !== 0) && (strpos($clean_url, '#') !== 0) && (strpos($clean_url, '?') !== 0)) {
        return $clean_url ? Url::fromUri($clean_url, $url_options) : $url;
      }
      if ($site_base_url) {
        $clean_url = $site_base_url . $clean_url;
        return $clean_url ? Url::fromUri($clean_url, $url_options) : $url;
      }
      return $clean_url ? Url::fromUserInput($clean_url, $url_options) : $url;
    } catch (Exception $exception) {
      watchdog_exception('tide_site_preview', $exception);
    }
    return '';
  }

}
