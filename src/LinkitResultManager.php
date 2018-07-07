<?php

namespace Drupal\tide_site;

use Drupal\Core\Path\AliasStorageInterface;
use Drupal\linkit\ProfileInterface;
use Drupal\linkit\ResultManager;

/**
 * Class LinkitResultManager.
 *
 * @package Drupal\tide_site
 */
class LinkitResultManager extends ResultManager {

  /**
   * The Alias Storage service.
   *
   * @var \Drupal\Core\Path\AliasStorageInterface
   */
  protected $aliasStorage;

  /**
   * The Alias Storage Helper service.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $aliasHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(AliasStorageInterface $alias_storage, AliasStorageHelper $alias_helper) {
    $this->aliasStorage = $alias_storage;
    $this->aliasHelper = $alias_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults(ProfileInterface $linkitProfile, $search_string) {
    $matches = parent::getResults($linkitProfile, $search_string);
    foreach ($matches as &$match) {
      $path = $this->aliasStorage->load(['alias' => $match['path']]);
      if ($path) {
        $node = $this->aliasHelper->getNodeFromPath($path);
        if ($node) {
          $match['path'] = '/node/' . $node->id();
        }
      }
    }

    return $matches;
  }

}
