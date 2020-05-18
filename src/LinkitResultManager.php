<?php

namespace Drupal\tide_site;

use Drupal\linkit\ProfileInterface;
use Drupal\linkit\SuggestionManager;

/**
 * Class LinkitResultManager.
 *
 * @package Drupal\tide_site
 */
class LinkitResultManager extends SuggestionManager {

  /**
   * The Alias Storage service.
   *
   * @var \Drupal\tide_site\AliasStorage
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
  public function __construct(AliasStorage $alias_storage, AliasStorageHelper $alias_helper) {
    $this->aliasStorage = $alias_storage;
    $this->aliasHelper = $alias_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(ProfileInterface $linkitProfile, $search_string) {
    $suggestions = parent::getSuggestions($linkitProfile, $search_string);
    foreach ($suggestions->getSuggestions() as $suggestion) {
      /** @var \Drupal\path_alias\PathAliasInterface[] $paths */
      $paths = $this->aliasStorage->loadAll(['path' => $suggestion->getPath()]);
      if ($paths) {
        foreach ($paths as $path) {
          $node = $this->aliasHelper->getNodeFromPath($path);
          if ($node) {
            $suggestion->setPath('/node/' . $node->id());
          }
        }
      }
    }

    return $suggestions;
  }

}
