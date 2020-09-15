<?php

namespace Drupal\tide_site;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\node\NodeInterface;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\path_alias\PathAliasInterface;
use Drupal\pathauto\AliasCleaner;
use Drupal\pathauto\pathautoGenerator;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for reacting to entity events.
 */
class TideSiteEntityOperations implements ContainerInjectionInterface {

  /**
   * AliasStorageHelper service.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $tideSiteAliasStorageHelper;

  /**
   * TideSiteHelper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $tideSiteHelper;

  /**
   * Path storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|mixed|object
   */
  protected $pathStorage;

  /**
   * PathautoGenerator service.
   *
   * @var \Drupal\pathauto\PathautoGenerator
   */
  protected $pathautoGenerator;

  /**
   * AliasCleaner service.
   *
   * @var \Drupal\pathauto\AliasCleaner
   */
  protected $pathautoAliasCleaner;

  /**
   * Constructs a new TideSiteEntityOperations object.
   *
   * @param \Drupal\tide_site\AliasStorageHelper $aliasStorageHelper
   *   AliasStorageHelper service.
   * @param \Drupal\tide_site\TideSiteHelper $siteHelper
   *   TideSiteHelper service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   EntityTypeManager service.
   * @param \Drupal\pathauto\PathautoGenerator $generator
   *   PathautoGenerator service.
   * @param \Drupal\pathauto\AliasCleaner $aliasCleaner
   *   AliasCleaner service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(AliasStorageHelper $aliasStorageHelper, TideSiteHelper $siteHelper, EntityTypeManager $entityTypeManager, PathautoGenerator $generator, AliasCleaner $aliasCleaner) {
    $this->tideSiteAliasStorageHelper = $aliasStorageHelper;
    $this->tideSiteHelper = $siteHelper;
    $this->pathStorage = $entityTypeManager->getStorage('path_alias');
    $this->pathautoGenerator = $generator;
    $this->pathautoAliasCleaner = $aliasCleaner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tide_site.alias_storage_helper'),
      $container->get('tide_site.helper'),
      $container->get('entity_type.manager'),
      $container->get('pathauto.generator'),
      $container->get('pathauto.alias_cleaner')
    );
  }

  /**
   * Acts on a node updating.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being updated.
   *
   * @see \hook_ENTITY_TYPE_update()
   */
  public function nodeUpdate(NodeInterface $node) {
    // If the node has no aliases, we should create them.
    if (!$this->tideSiteAliasStorageHelper->loadAll(['path' => '/node/' . $node->id()])) {
      $this->pathautoGenerator->createEntityAlias($node, 'update');
    }
    // Get last node revision for comparison purpose.
    $original_node = $node->original;
    // If node title changed, we should change the aliases accordingly.
    if ($node->getTitle() !== $original_node->getTitle()) {
      $new_uri_without_site_prefix = $this->pathautoAliasCleaner->cleanString($node->getTitle());
      $existing_path_aliases = $this->tideSiteAliasStorageHelper->loadAll(['path' => '/node/' . $node->id()]);
      foreach ($existing_path_aliases as $existing_path_alias) {
        $path = $existing_path_alias->getAlias();
        $site_id = $this->tideSiteHelper->getSiteIdFromSitePrefix($path);
        // If site id cannot be found, just skip it.
        if (!$site_id) {
          continue;
        }
        $changed_alias = '/site-' . $site_id . '/' . $new_uri_without_site_prefix;
        $this->tideSiteAliasStorageHelper->uniquify($changed_alias, $existing_path_alias->language()
          ->getId());
        $existing_path_alias->setAlias($changed_alias)->save();
      }
    }
    $this->tideSiteAliasStorageHelper->deleteOrCreateFromSites($original_node, $node);
  }

  /**
   * Acts on a path alias entity being inserted.
   *
   * @param \Drupal\path_alias\Entity\PathAliasInterface $pathAlias
   *   The path alias being inserted.
   *
   * @see \hook_ENTITY_TYPE_insert()
   */
  public function pathAliasInsert(PathAliasInterface $pathAlias) {
    $node = $this->tideSiteAliasStorageHelper->getNodeFromPathEntity($pathAlias);
    if ($node && !$this->tideSiteAliasStorageHelper->isPathHasSitePrefix($pathAlias)) {
      $this->tideSiteAliasStorageHelper->createSiteAliases($pathAlias, $node);
      if ($this->tideSiteHelper->getEntitySites($node)) {
        if (!$this->tideSiteAliasStorageHelper->isPathHasSitePrefix($pathAlias)) {
          $pathAlias->delete();
        }
      }
    }
  }

  /**
   * Acts on a path alias entity being updated.
   *
   * @param \Drupal\path_alias\Entity\PathAliasInterface $pathAlias
   *   The path alias being updated.
   *
   * @see \hook_ENTITY_TYPE_update()
   */
  public function pathAliasUpdate(PathAliasInterface $pathAlias) {
    $node = $this->tideSiteAliasStorageHelper->getNodeFromPathEntity($pathAlias);
    if ($node && !$this->tideSiteAliasStorageHelper->isPathHasSitePrefix($pathAlias)) {
      $this->tideSiteAliasStorageHelper->updateSiteAliases($pathAlias, $pathAlias->original);
      if ($this->tideSiteHelper->getEntitySites($node)) {
        // Delete the current path if it does not have site prefix.
        if (!$this->tideSiteAliasStorageHelper->isPathHasSitePrefix($pathAlias)) {
          $pathAlias->delete();
        }
      }
    }
  }

  /**
   * Acts on a path alias entity being deleted.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   The entity being deleted.
   *
   * @see \hook_ENTITY_TYPE_delete()
   */
  public function termDelete(TermInterface $term) {
    // Delete all site aliases of content belong to this Site term.
    if ($term->bundle() == 'sites') {
      $site_prefix = $this->tideSiteHelper->getSitePathPrefix($term);
      $path_ids = \Drupal::entityQuery('path_alias')
        ->condition('alias', $site_prefix . '/', 'CONTAINS')
        ->condition('path', '/taxonomy/term/' . $term->id(), '=')
        ->execute();
      $this->pathStorage->delete(PathAlias::loadMultiple($path_ids));
    }
  }

}
