<?php

namespace Drupal\tide_site;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\pathauto\AliasUniquifierInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class AliasStorageHelper.
 *
 * @package Drupal\tide_site
 */
class AliasStorageHelper {
  use ContainerAwareTrait;

  const ALIAS_SCHEMA_MAX_LENGTH = 255;

  /**
   * Tide Site Helper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $helper;

  /**
   * Alias Storage service.
   *
   * @var \Drupal\tide_site\AliasStorage
   */
  protected $aliasStorage;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Alias uniquifier service.
   *
   * @var \Drupal\pathauto\AliasUniquifierInterface
   */
  protected $aliasUniquifier;

  /**
   * AliasStorageHelper constructor.
   *
   * @param \Drupal\tide_site\TideSiteHelper $helper
   *   Tide Site Helper service.
   * @param \Drupal\tide_site\AliasStorage $alias_storage
   *   Alias Storage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   */
  public function __construct(TideSiteHelper $helper, AliasStorage $alias_storage, EntityTypeManagerInterface $entity_type_manager) {
    $this->helper = $helper;
    $this->aliasStorage = $alias_storage;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Set the Alias Uniquifier service.
   *
   * @param \Drupal\pathauto\AliasUniquifierInterface $alias_uniquifier
   *   The service.
   */
  public function setAliasUniquifier(AliasUniquifierInterface $alias_uniquifier) {
    $this->aliasUniquifier = $alias_uniquifier;
  }

  /**
   * Get the Alias Uniquifier service.
   *
   * @return \Drupal\pathauto\AliasUniquifierInterface
   *   The service.
   */
  public function getAliasUniquifier() {
    if (empty($this->aliasUniquifier)) {
      $this->setAliasUniquifier($this->container->get('pathauto.alias_uniquifier'));
    }
    return $this->aliasUniquifier;
  }

  /**
   * Check if an alias is a site alias.
   *
   * @param array $path
   *   The path array.
   *
   * @return bool
   *   TRUE if site alias.
   */
  public function isPathHasSitePrefix(array $path) {
    return (boolean) preg_match('/^\/site\-(\d+)\//', $path['alias']);
  }

  /**
   * Load the node from a path.
   *
   * @param array|mixed $path
   *   The path.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node object, or NULL.
   */
  public function getNodeFromPath($path) {
    $node = NULL;
    if (!empty($path['source'])) {
      try {
        $uri = Url::fromUri('internal:' . $path['source']);
        if ($uri->isRouted() && $uri->getRouteName() == 'entity.node.canonical') {
          $params = $uri->getRouteParameters();
          if (isset($params['node'])) {
            $node = $this->entityTypeManager->getStorage('node')->load($params['node']);
          }
        }
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_site', $exception);
      }
    }

    return $node;
  }

  /**
   * Extract the original alias without site prefix.
   *
   * @param array $path
   *   The path.
   * @param string $site_base_url
   *   The site base URL if the path alias is an absolute URL.
   *
   * @return string
   *   The raw internal alias without site prefix.
   */
  public function getPathAliasWithoutSitePrefix(array $path, $site_base_url = '') {
    $pattern = '/^\/site\-(\d+)\//';
    if ($site_base_url) {
      $pattern = '/' . preg_quote($site_base_url, '/') . '\/site\-(\d+)\//';
    }
    return preg_replace($pattern, $site_base_url . '/', $path['alias']);
  }

  /**
   * Retrieve a list of aliases with site prefix from a path.
   *
   * @param array|mixed $path
   *   The path.
   * @param \Drupal\node\NodeInterface|null $node
   *   The node (optional).
   *
   * @return string[]
   *   The list of aliases, keyed by site ID.
   */
  public function getAllSiteAliases($path, NodeInterface $node = NULL) {
    $aliases = [];
    if (!$node) {
      $node = $this->getNodeFromPath($path);
    }

    if ($node) {
      $original_alias = $this->getPathAliasWithoutSitePrefix($path);
      $sites = $this->helper->getEntitySites($node, TRUE);
      if ($sites) {
        foreach ($sites['ids'] as $site_id) {
          $site_prefix = $this->helper->getSitePathPrefix($site_id);
          $aliases[$site_id] = $site_prefix . $original_alias;
        }
      }
    }

    return $aliases;
  }

  /**
   * Create all site aliases of a path.
   *
   * @param array|mixed $path
   *   The Path array.
   * @param \Drupal\node\NodeInterface|null $node
   *   The node (optional).
   * @param int[] $site_ids
   *   The list of site to create alias (optional).
   */
  public function createSiteAliases($path, NodeInterface $node = NULL, array $site_ids = []) {
    if (!$node) {
      $node = $this->getNodeFromPath($path);
    }

    if ($node) {
      $this->getAliasUniquifier();

      $aliases = $this->getAllSiteAliases($path, $node);

      if (!empty($site_ids)) {
        $site_ids = array_combine($site_ids, $site_ids);
        array_intersect_key($aliases, $site_ids);
      }

      foreach ($aliases as $alias) {
        try {
          $original_alias = $alias;

          $existing_path = $this->isAliasExists($alias, $path['langcode']);
          if ($existing_path) {
            if ($existing_path['source'] != $path['source']) {
              $this->uniquify($alias, $path['langcode']);
              if ($original_alias != $alias) {
                $this->aliasStorage->save($path['source'], $alias, $path['langcode'], NULL, FALSE);
              }
            }
          }
          else {
            $this->aliasStorage->save($path['source'], $alias, $path['langcode'], NULL, FALSE);
          }
        }
        catch (\Exception $exception) {
          watchdog_exception('tide_site', $exception);
        }
      }
    }
  }

  /**
   * Update all site aliases of a path.
   *
   * @param array|mixed $path
   *   The new path.
   * @param array|mixed $original_path
   *   The original path.
   */
  public function updateSiteAliases($path, $original_path) {
    if (!is_array($path) || !is_array($original_path)) {
      return;
    }

    $node = $this->getNodeFromPath($path);
    if ($node) {
      $aliases = $this->getAllSiteAliases($path, $node);
      $original_aliases = $this->getAllSiteAliases($original_path, $node);
      foreach ($aliases as $site_id => $alias) {
        if ($alias == $path['alias']) {
          // This alias already exists.
          continue;
        }
        // Find the old path to update.
        $old_path = $this->aliasStorage->load([
          'source' => $path['source'],
          'alias' => $original_aliases[$site_id],
        ]);
        if (!$old_path) {
          $old_path['pid'] = NULL;
        }
        try {
          if (!$this->isAliasExists($alias, $path['langcode'])) {
            $this->aliasStorage->save($path['source'], $alias, $path['langcode'], $old_path['pid'], FALSE);
          }
        }
        catch (\Exception $exception) {
          watchdog_exception('tide_site', $exception);
        }
      }
    }
  }

  /**
   * Delete all site copies of a path alias.
   *
   * @param array|bool $path
   *   The Path array.
   */
  public function deleteSiteAliases($path) {
    $node = $this->getNodeFromPath($path);
    if ($node) {
      $aliases = $this->getAllSiteAliases($path, $node);
      foreach ($aliases as $alias) {
        try {
          $this->aliasStorage->delete([
            'source' => $path['source'],
            'alias' => $alias,
          ], FALSE);
        }
        catch (\Exception $exception) {
          watchdog_exception('tide_site', $exception);
        }
      }
    }
  }

  /**
   * Regenerate site aliases for a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param int[] $site_ids
   *   List if site to regenerate.
   */
  public function regenerateNodeSiteAliases(NodeInterface $node, array $site_ids = []) {
    // Collect all existing aliases of the node.
    $aliases = [];
    $path_aliases = $this->aliasStorage->loadAll(['source' => '/node/' . $node->id()]);
    foreach ($path_aliases as $path) {
      // Group them by language and original alias without site prefix.
      $alias = $this->getPathAliasWithoutSitePrefix($path);
      $aliases[$path['langcode'] . ':' . $alias] = $path;
    }
    // Regenerate aliases.
    foreach ($aliases as $path) {
      $this->createSiteAliases($path, $node, $site_ids);
    }
  }

  /**
   * Check if an alias exists.
   *
   * @param string $alias
   *   The alias.
   * @param string $langcode
   *   The language code.
   *
   * @return array|false
   *   FALSE if does not exist.
   */
  public function isAliasExists($alias, $langcode = '') {
    $conditions = ['alias' => $alias];
    if ($langcode) {
      $conditions['langcode'] = $langcode;
    }
    $path = $this->aliasStorage->load($conditions);

    return $path ?: FALSE;
  }

  /**
   * Attempt to generate a unique alias.
   *
   * @param string $alias
   *   The alias.
   * @param string $langcode
   *   The language code.
   */
  public function uniquify(&$alias, $langcode) {
    if (!$this->isAliasExists($alias, $langcode)) {
      return;
    }

    // If the alias already exists, generate a new, hopefully unique, variant.
    $maxlength = static::ALIAS_SCHEMA_MAX_LENGTH;
    $separator = '-';
    $original_alias = $alias;

    $i = 0;
    do {
      // Append an incrementing numeric suffix until we find a unique alias.
      $unique_suffix = $separator . $i;
      $alias = Unicode::truncate($original_alias, $maxlength - Unicode::strlen($unique_suffix), TRUE) . $unique_suffix;
      $i++;
    } while ($this->isAliasExists($alias, $langcode));
  }

}
