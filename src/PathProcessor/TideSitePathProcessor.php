<?php

namespace Drupal\tide_site\PathProcessor;

use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for tide_site module.
 */
class TideSitePathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Constructs a PathProcessorAlias object.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   An alias manager for looking up the system path.
   */
  public function __construct(AliasManagerInterface $alias_manager) {
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $path = $this->aliasManager->getPathByAlias($path);
    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (empty($options['alias'])) {
      $langcode = isset($options['language']) ? $options['language']->getId() : NULL;
      $pattern = "\/node\/[0-9]*";
      if (preg_match('/^' . $pattern . '$/', $path)) {
        if (preg_match("/\/(\d+)$/", $path, $matches)) {
          $nid = $matches[1];
          $node = Node::load($nid);
          /** @var \Drupal\tide_site\TideSiteHelper $helper */
          $helper = \Drupal::service('tide_site.helper');
          /** @var \Drupal\tide_site\AliasStorageHelper $alias_helper */
          $alias_helper = \Drupal::service('tide_site.alias_storage_helper');
          $site = $helper->getEntityPrimarySite($node);
          $path = $this->aliasManager->getAliasByPath($path, $langcode);
          if (!preg_match('/^' . $pattern . '$/', $path)) {
            if ($site) {
              $path_without_site = $alias_helper->getPathAliasWithoutSitePrefix(['alias' => $path]);
              $path = '/site-' . $site->id() . $path_without_site;
            }
          }
        }
      }
      else {
        $path = $this->aliasManager->getAliasByPath($path, $langcode);
      }
      if (strpos($path, '//') === 0) {
        $path = '/' . ltrim($path, '/');
      }
    }
    return $path;
  }

}
