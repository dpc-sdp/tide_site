<?php

namespace Drupal\tide_site;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\linkit\ProfileInterface;
use Drupal\linkit\SuggestionManager;

/**
 * Class LinkitResultManager.
 *
 * @package Drupal\tide_site
 */
class LinkitResultManager extends SuggestionManager {

  /**
   * The Alias Storage Helper service.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $aliasHelper;

  /**
   * The Path alias entity storage.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AliasStorageHelper $alias_helper, EntityTypeManager $entityTypeManager) {
    $this->aliasHelper = $alias_helper;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getSuggestions(ProfileInterface $linkitProfile, $search_string) {
    $suggestions = parent::getSuggestions($linkitProfile, $search_string);
    foreach ($suggestions->getSuggestions() as $suggestion) {
      /** @var \Drupal\path_alias\PathAliasInterface[] $paths */
      $paths = $this->entityTypeManager->getStorage('path_alias')
        ->loadByProperties(['path' => $suggestion->getPath()]);
      if ($paths) {
        foreach ($paths as $path) {
          $node = $this->aliasHelper->getNodeFromPathEntity($path);
          if ($node) {
            $suggestion->setPath('/node/' . $node->id());
          }
        }
      }
    }

    return $suggestions;
  }

}
