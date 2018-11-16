<?php

namespace Drupal\tide_site;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Path\AliasStorage as CoreAliasStorage;

/**
 * Class AliasStorage.
 *
 * @package Drupal\tide_site
 */
class AliasStorage extends CoreAliasStorage {

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Path\AliasStorage::save()
   */
  public function save($source, $alias, $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED, $pid = NULL, $invokeHooks = TRUE) {
    if ($source[0] !== '/') {
      throw new \InvalidArgumentException(sprintf('Source path %s has to start with a slash.', $source));
    }

    if ($alias[0] !== '/') {
      throw new \InvalidArgumentException(sprintf('Alias path %s has to start with a slash.', $alias));
    }

    $fields = [
      'source' => $source,
      'alias' => $alias,
      'langcode' => $langcode,
    ];

    // Insert or update the alias.
    if (empty($pid)) {
      $try_again = FALSE;
      try {
        $query = $this->connection->insert(static::TABLE)
          ->fields($fields);
        $pid = $query->execute();
      }
      catch (\Exception $e) {
        // If there was an exception, try to create the table.
        if (!$try_again = $this->ensureTableExists()) {
          // If the exception happened for other reason than the missing table,
          // propagate the exception.
          throw $e;
        }
      }
      // Now that the table has been created, try again if necessary.
      if ($try_again) {
        $query = $this->connection->insert(static::TABLE)
          ->fields($fields);
        $pid = $query->execute();
      }

      $fields['pid'] = $pid;
      $operation = 'insert';
    }
    else {
      // Fetch the current values so that an update hook can identify what
      // exactly changed.
      try {
        $original = $this->connection->query('SELECT source, alias, langcode FROM {url_alias} WHERE pid = :pid', [':pid' => $pid])
          ->fetchAssoc();
      }
      catch (\Exception $e) {
        $this->catchException($e);
        $original = FALSE;
      }
      $fields['pid'] = $pid;
      $query = $this->connection->update(static::TABLE)
        ->fields($fields)
        ->condition('pid', $pid);
      $pid = $query->execute();
      $fields['original'] = $original;
      $operation = 'update';
    }
    if ($pid) {
      if ($invokeHooks) {
        // @todo Switch to using an event for this instead of a hook.
        // @todo Disable invoke all for path_update becaue generate an infinity redirect
        // $this->moduleHandler->invokeAll('path_' . $operation, [$fields]);
      }
      Cache::invalidateTags(['route_match']);
      return $fields;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\Core\Path\AliasStorage::delete()
   */
  public function delete($conditions, $invokeHooks = TRUE, $escapeLike = TRUE) {
    $path = $this->load($conditions);
    $query = $this->connection->delete(static::TABLE);
    foreach ($conditions as $field => $value) {
      if ($field == 'source' || $field == 'alias') {
        // Use LIKE for case-insensitive matching.
        $query->condition($field, $escapeLike ? $this->connection->escapeLike($value) : $value, 'LIKE');
      }
      else {
        $query->condition($field, $value);
      }
    }
    try {
      $deleted = $query->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
      $deleted = FALSE;
    }

    if ($invokeHooks && $path) {
      // @todo Switch to using an event for this instead of a hook.
      $this->moduleHandler->invokeAll('path_delete', [$path]);
    }
    Cache::invalidateTags(['route_match']);
    return $deleted;
  }

  /**
   * Fetches specific URL aliases from the database.
   *
   * The default implementation performs case-insensitive matching on the
   * 'source' and 'alias' strings.
   *
   * @param array $conditions
   *   An array of query conditions.
   *
   * @return array|false
   *   FALSE if no alias was found or an associative array containing the
   *   following keys:
   *   - source (string): The internal system path with a starting slash.
   *   - alias (string): The URL alias with a starting slash.
   *   - pid (int): Unique path alias identifier.
   *   - langcode (string): The language code of the alias.
   */
  public function loadAll(array $conditions) {
    $select = $this->connection->select(static::TABLE);
    foreach ($conditions as $field => $value) {
      if ($field == 'source' || $field == 'alias') {
        // Use LIKE for case-insensitive matching.
        $select->condition($field, $this->connection->escapeLike($value), 'LIKE');
      }
      elseif ($field == 'langcode') {
        $select->condition($field, [$value, LanguageInterface::LANGCODE_NOT_SPECIFIED], 'IN');
      }
      else {
        $select->condition($field, $value);
      }
    }
    try {
      return $select
        ->fields(static::TABLE)
        ->orderBy('pid', 'DESC')
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
    }
    catch (\Exception $e) {
      $this->catchException($e);
      return FALSE;
    }
  }

}
