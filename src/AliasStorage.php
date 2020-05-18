<?php

namespace Drupal\tide_site;

use Drupal\path_alias\AliasRepository as CoreAliasStorage;

/**
 * Class AliasStorage.
 *
 * @package Drupal\tide_site
 */
class AliasStorage extends CoreAliasStorage {

  /**
   * Fetches specific URL aliases from the database.
   *
   * The default implementation performs case-insensitive matching on the
   * 'source' and 'alias' strings.
   *
   * @param array $conditions
   *   An array of query conditions.
   *
   * @return \Drupal\path_alias\PathAliasInterface[]|false
   *   FALSE if no alias was found or an associative array containing the
   *   following keys:
   *   - path (string): The internal system path with a starting slash.
   *   - alias (string): The URL alias with a starting slash.
   *   - id (int): Unique path alias identifier.
   *   - langcode (string): The language code of the alias.
   */
  public function loadAll(array $conditions) {
    $path_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $paths = $path_storage->loadByProperties($conditions);
    if (!$paths) {
      return FALSE;
    }
    return $paths;
  }

}
