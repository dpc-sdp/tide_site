<?php

namespace Drupal\tide_site;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class AliasStorageHelper.
 *
 * @package Drupal\tide_site
 */
class AliasStorageHelper {
  use ContainerAwareTrait;

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
   * @param string $site_url
   *   The site URL with scheme and domain if the path alias is an absolute URL.
   *
   * @return string
   *   The raw internal alias without site prefix.
   */
  public function getPathAliasWithoutSitePrefix(array $path, $site_url = '') {
    $pattern = '/^\/site\-(\d+)\//';
    if ($site_url) {
      $pattern = '/' . preg_quote($site_url, '/') . '\/site\-(\d+)\//';
    }
    return preg_replace($pattern, $site_url . '/', $path['alias']);
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
   */
  public function createSiteAliases($path, NodeInterface $node = NULL) {
    if (!$node) {
      $node = $this->getNodeFromPath($path);
    }

    if ($node) {
      $aliases = $this->getAllSiteAliases($path, $node);
      foreach ($aliases as $alias) {
        $existing_alias = $this->aliasStorage->load([
          'alias' => $alias,
          'source'  => $path['source'],
          'langcode' => $path['langcode'],
        ]);
        if (!$existing_alias) {
          try {
            $this->aliasStorage->save($path['source'], $alias, $path['langcode'], NULL, FALSE);
          }
          catch (\Exception $exception) {
            watchdog_exception('tide_site', $exception);
          }
        }
      }
    }
  }

  /**
   * Update all site aliases of a path.
   *
   * @param array|mixed $path
   *   The new path.
   * @param array|mixed $old_path
   *   The old path.
   */
  public function updateSiteAliases($path, $old_path) {
    if (!is_array($path) || !is_array($old_path)) {
      return;
    }

    $node = $this->getNodeFromPath($path);
    if ($node) {
      $aliases = $this->getAllSiteAliases($path, $node);
      $old_aliases = $this->getAllSiteAliases($old_path, $node);
      foreach ($aliases as $site_id => $alias) {
        if ($alias == $path['alias']) {
          // This alias already exists.
          continue;
        }
        // Find the old alias to update.
        $old_alias = $this->aliasStorage->load([
          'source' => $path['source'],
          'alias' => $old_aliases[$site_id],
        ]);
        if (!$old_alias) {
          $old_alias['pid'] = NULL;
        }
        try {
          $this->aliasStorage->save($path['source'], $alias, $path['langcode'], $old_alias['pid'], FALSE);
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
   */
  public function regenerateNodeSiteAliases(NodeInterface $node) {
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
      $this->createSiteAliases($path, $node);
    }
  }

}
