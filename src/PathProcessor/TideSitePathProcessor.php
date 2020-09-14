<?php

namespace Drupal\tide_site\PathProcessor;

use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for tide_site module.
 */
class TideSitePathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {
  use ContainerAwareTrait;

  /**
   * An alias manager for looking up the system path.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * Tide site helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $tideSiteHelper;

  /**
   * Tide path alias helper.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $tidAliasHelper;

  /**
   * Constructs a PathProcessorAlias object.
   *
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   An alias manager for looking up the system path.
   * @param \Drupal\tide_site\TideSiteHelper $siteHelper
   *   Tide site helper.
   * @param \Drupal\tide_site\AliasStorageHelper $aliasStorageHelper
   *   Tide path alias helper.
   */
  public function __construct(AliasManagerInterface $alias_manager, TideSiteHelper $siteHelper, AliasStorageHelper $aliasStorageHelper) {
    $this->aliasManager = $alias_manager;
    $this->tideSiteHelper = $siteHelper;
    $this->tideAliasHelper = $aliasStorageHelper;
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
      // We only care about /node/{id} path.
      if (preg_match('/^' . $pattern . '$/', $path)) {
        // If it is an node canonical url, we load it.
        if (preg_match("/\/(\d+)$/", $path, $matches)) {
          $nid = $matches[1];
          $node = Node::load($nid);
          $aliases = $this->tideAliasHelper->loadAll(['path' => $path]);
          // Gets PrimarySite term entity.
          $site = $this->tideSiteHelper->getEntityPrimarySite($node);
          $path = $this->aliasManager->getAliasByPath($path, $langcode);
          if ($site && $aliases) {
            foreach ($aliases as $pathAlias) {
              if (strpos($pathAlias->getAlias(), '/site-' . $site->id() . '/') !== FALSE) {
                $path = $pathAlias->getAlias();
              }
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
