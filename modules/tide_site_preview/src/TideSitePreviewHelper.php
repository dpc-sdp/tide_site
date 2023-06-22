<?php

namespace Drupal\tide_site_preview;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tide_site\TideSiteHelper;

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
  public function buildFrontendPreviewLink(NodeInterface $node, TermInterface $site, TermInterface $section = NULL, array $configuration = []) : array {
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
      $preview_link['url'] = $this->getNodeFrontendUrl($node, $site_base_url, $url_options);
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
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $site_base_url
   *   The base URL of the frontend.
   * @param array $url_options
   *   The extra options.
   *
   * @return \Drupal\Core\Url|string
   *   The Url.
   */
  public function getNodeFrontendUrl(NodeInterface $node, $site_base_url = '', array $url_options = []) {
    try {
      $url = $node->toUrl('canonical', [
        'absolute' => TRUE,
        'base_url' => $site_base_url,
      ] + $url_options);

      $pattern = '/^\/site\-(\d+)\//';
      if ($site_base_url) {
        $pattern = '/' . preg_quote($site_base_url, '/') . '\/site\-(\d+)\//';
      }
      $clean_url = preg_replace($pattern, $site_base_url . '/', $url->toString());
      return $clean_url ? Url::fromUri($clean_url, $url_options) : $url;
    }
    catch (Exception $exception) {
      watchdog_exception('tide_site_preview', $exception);
    }
    return '';
  }

}
