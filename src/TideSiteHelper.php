<?php

namespace Drupal\tide_site;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Class TideSiteHelper.
 *
 * @package Drupal\tide_site
 */
class TideSiteHelper {
  use ContainerAwareTrait;

  /**
   * List of allowed entity types.
   *
   * @var array
   */
  protected $supportedEntityTypes = ['node', 'media'];

  /**
   * List of entity types restricted from accessing by Site.
   *
   * @var array
   */
  protected $restrictedEntityTypes = ['node'];

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity Repository service.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * Returns list of supported entity types.
   *
   * @return array
   *   Array of supported entity types.
   */
  public function getSupportedEntityTypes(): array {
    return $this->supportedEntityTypes;
  }

  /**
   * Check if an entity type is supported.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if the entity type is supported.
   */
  public function isSupportedEntityType($entity_type) {
    return in_array($entity_type, $this->supportedEntityTypes);
  }

  /**
   * Check if an entity type is restricted from being accessed by Site.
   *
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   TRUE if the entity type is restricted.
   */
  public function isRestrictedEntityType($entity_type) {
    return in_array($entity_type, $this->restrictedEntityTypes);
  }

  /**
   * Returns all sites.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   List of sites.
   */
  public function getAllSites() {
    $sites = [];

    try {
      $tree = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadTree('sites', 0, 1, TRUE);
      /** @var \Drupal\taxonomy\TermInterface $site */
      foreach ($tree as $site) {
        $sites[$site->id()] = $site;
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_site', $exception);
    }

    return $sites;
  }

  /**
   * Loads a Site term by ID.
   *
   * @param int $tid
   *   The Site ID.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The Site term, or NULL if not exists.
   */
  public function getSiteById($tid) {
    try {
      $site = $this->entityTypeManager->getStorage('taxonomy_term')
        ->load($tid);

      return $site;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Check if a Site ID is valid.
   *
   * Section ID is not valid.
   *
   * @param int $tid
   *   The Site ID.
   *
   * @return bool
   *   TRUE if the Site ID is valid.
   */
  public function isValidSite($tid) {
    $trail = $this->getSiteTrail($tid);
    if ($trail && count($trail) == 1) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Returns the term trail of a Site or Section.
   *
   * @param int $tid
   *   The Site/Section ID.
   *
   * @return array|null
   *   The term tail.
   */
  public function getSiteTrail($tid) {
    try {
      $ancestors = $this->entityTypeManager->getStorage('taxonomy_term')
        ->loadAllParents($tid);
      $trail = [];
      foreach ($ancestors as $term) {
        /** @var \Drupal\taxonomy\TermInterface $term */
        $trail[] = $term->id();
      }

      return array_reverse($trail);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Returns the domains of a site.
   *
   * @param \Drupal\taxonomy\TermInterface|null $site
   *   The site term.
   *
   * @return string[]
   *   The list of domains.
   */
  public function getSiteDomains(TermInterface $site = NULL) {
    $domains = [];
    if ($site && $site->hasField('field_site_domains')) {
      $domains = $site->get('field_site_domains')->getString();
      $domains = preg_split('/\R/', $domains) ?: [];
      foreach ($domains as &$domain) {
        $domain = rtrim($domain, '/');
      }
    }
    return $domains;
  }

  /**
   * Returns the production domain of a Site.
   *
   * @param \Drupal\taxonomy\TermInterface|null $site
   *   The site term.
   *
   * @return string
   *   The domain.
   */
  public function getSiteProductionDomain(TermInterface $site = NULL) {
    $domain = '';
    $domains = $this->getSiteDomains($site);
    if ($domains) {
      $domain = reset($domains);
    }

    return $domain;
  }

  /**
   * Get the homepage entity of a site.
   *
   * @param \Drupal\taxonomy\TermInterface|null $site
   *   The site term.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The homepage entity.
   */
  public function getSiteHomepageEntity(TermInterface $site = NULL) {
    $homepage = NULL;
    if ($site && $site->hasField('field_site_homepage') && !$site->get('field_site_homepage')->isEmpty()) {
      try {
        $referencedEntities = $site->get('field_site_homepage')
          ->referencedEntities();
        if (!empty($referencedEntities)) {
          $homepage = reset($referencedEntities);
        }
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_site', $exception);
      }
    }
    return $homepage;
  }

  /**
   * Returns the site path prefix based on site name.
   *
   * @param \Drupal\taxonomy\TermInterface|int $site
   *   The Site term, or Site ID.
   *
   * @return string
   *   The path prefix.
   */
  public function getSitePathPrefix($site) {
    if ($site instanceof TermInterface) {
      $site_id = $site->id();
    }
    else {
      $site_id = $site;
    }
    return '/site-' . $site_id;
  }

  /**
   * Check if an internal path has site prefix.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   TRUE if the path has site prefix.
   */
  public function hasSitePrefix($path) {
    return preg_match("/^\/site\-(\d+)\//", $path);
  }

  /**
   * Retrieve the Site ID from the Site Prefix of an internal path.
   *
   * @param string $path
   *   The path to check.
   *
   * @return int|null
   *   The site ID. Null if the path does not have site prefix.
   */
  public function getSiteIdFromSitePrefix($path) {
    if (preg_match("/^\/site\-(\d+)\//", $path, $matches) && !empty($matches[1])) {
      return $matches[1];
    }

    return NULL;
  }

  /**
   * Return the absolute URL of a site using its production domain.
   *
   * @param \Drupal\taxonomy\TermInterface $site
   *   The site.
   * @param string $scheme
   *   The scheme, default to https.
   *
   * @return string
   *   The base URL without trailing slashes.
   */
  public function getSiteBaseUrl(TermInterface $site, $scheme = 'https') {
    $url = '';

    $domain = $this->getSiteProductionDomain($site);
    if ($domain) {
      $url = rtrim($scheme . '://' . $domain, '/');
    }
    return $url;
  }

  /**
   * Returns the Sites of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The Entity object.
   * @param bool $reset
   *   Whether to reset cache.
   *
   * @return array|null
   *   The Sites array with 2 keys:
   *     - ids      : List of site ids.
   *     - sections : List of sections: site_id => section_id.
   */
  public function getEntitySites(FieldableEntityInterface $entity, $reset = FALSE) {
    // Static cache as this method maybe called multiple times per request.
    $static_cache = &drupal_static(__FUNCTION__, []);
    if ($reset) {
      unset($static_cache[$entity->id()]);
    }
    if (isset($static_cache[$entity->id()])) {
      return $static_cache[$entity->id()];
    }

    $entity_type = $entity->getEntityTypeId();
    $sites = NULL;

    if ($this->isSupportedEntityType($entity_type)) {
      $cid = $this->getSitesCacheId($entity);
      if ($reset) {
        $this->cache('data')->delete($cid);
      }
      // Attempt to load from data cache.
      $cached_sites = $this->cache('data')->get($cid);
      if ($cached_sites) {
        $sites = $cached_sites->data;
      }
      // Cache miss.
      else {
        $field_site_field_name = TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_SITE, $entity_type);
        // Only process if the entity has Site field.
        if ($entity->hasField($field_site_field_name)) {
          $field_site = $entity->get($field_site_field_name);
          // Only process if its Site field has values.
          if (!$field_site->isEmpty()) {
            $cache_tags = [];
            // Fetch the trail of every term.
            $trails = [];
            foreach ($field_site->getValue() as $value) {
              $term_id = $value['target_id'];
              // Build the trail for each term.
              $trail = $this->getSiteTrail($term_id);
              $trails[$term_id] = $trail;
              // This term is a Site not a sub-section.
              if (count($trail) == 1) {
                $sites['ids'][$term_id] = $term_id;
              }
              $cache_tags[] = 'taxonomy_term:' . $term_id;
            }
            if (empty($sites['ids'])) {
              $sites = ['ids' => [], 'sections' => []];
            }
            // Build the sections. Each Site should only have one section.
            foreach ($sites['ids'] as $site_id) {
              $sites['sections'][$site_id] = $site_id;
              foreach ($trails as $trail) {
                $trail_start = reset($trail);
                $trail_end = end($trail);
                if ($trail_end != $site_id) {
                  $sites['sections'][$trail_start] = $trail_end;
                }
              }
            }

            // Cache the results.
            if (!$reset) {
              $this->cache('data')->set($cid, $sites, Cache::PERMANENT, Cache::mergeTags($cache_tags, $entity->getCacheTags()));
              $static_cache[$entity->id()] = $sites;
            }
          }
        }
      }
    }

    return $sites;
  }

  /**
   * Returns the Primary Site of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The Entity object.
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   *   The Primary site.
   */
  public function getEntityPrimarySite(FieldableEntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $primary_site = NULL;

    try {
      if ($this->isSupportedEntityType($entity_type)) {
        $field_primary_site_field_name = TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_PRIMARY_SITE, $entity_type);
        if ($entity->hasField($field_primary_site_field_name)) {
          /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $field_value */
          $field_value = $entity->get($field_primary_site_field_name);
          if (!$field_value->isEmpty()) {
            $sites = $field_value->referencedEntities();
            $primary_site = reset($sites);
          }
        }
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_site', $exception);
    }

    return $primary_site;
  }

  /**
   * Check if an entity belongs to a Site.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object.
   * @param int $site_id
   *   The Site ID.
   *
   * @return bool
   *   TRUE if the entity belongs to the given Site.
   */
  public function isEntityBelongToSite(FieldableEntityInterface $entity, $site_id) {
    $entity_type = $entity->getEntityTypeId();
    if ($this->isSupportedEntityType($entity_type)) {
      $entity_sites = $this->getEntitySites($entity);
      if ($entity_sites && count($entity_sites)) {
        return in_array($site_id, $entity_sites['ids']);
      }
    }

    return FALSE;
  }

  /**
   * Load an entity by its UUID.
   *
   * @param string $uuid
   *   The UUID.
   * @param string $entity_type
   *   The entity type, eg. node or media.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The Entity. NULL if not found.
   */
  public function getEntityByUuid($uuid, $entity_type) {
    try {
      return $this->entityRepository->loadEntityByUuid($entity_type, $uuid);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Load an entity by its ID.
   *
   * @param string $id
   *   The ID.
   * @param string $entity_type
   *   The entity type, eg. node or media.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The Entity. NULL if not found.
   */
  public function getEntityById($id, $entity_type) {
    try {
      return $this->entityTypeManager->getStorage($entity_type)->load($id);
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Get the URL of the primary site of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The node.
   *
   * @return \Drupal\Core\GeneratedUrl|string
   *   The URL.
   */
  public function getEntityPrimarySiteBaseUrl(FieldableEntityInterface $entity) {
    $url = '';

    // Fetch its primary site instead.
    $site = $this->getEntityPrimarySite($entity);
    if ($site) {
      /** @var \Symfony\Component\HttpFoundation\Request $request */
      $request = $this->container->get('request_stack')->getCurrentRequest();
      $url = $this->getSiteBaseUrl($site, $request->getScheme());
    }
    return $url;
  }

  /**
   * Get the URL of node using its primary site.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return string
   *   The URL.
   */
  public function getNodeUrlFromPrimarySite(NodeInterface $node) {
    $url = '/node/' . $node->id();

    $primary_site_url = $this->getEntityPrimarySiteBaseUrl($node);
    if ($primary_site_url) {
      // Return the absolute URL.
      $url = $node->toUrl('canonical', [
        'absolute' => TRUE,
        'base_url' => $primary_site_url,
      ])->toString();
    }

    return $url;
  }

  /**
   * Returns URLs of an entity for all sites.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $scheme
   *   Default to https.
   *
   * @return string[]
   *   The list of URLs.
   */
  public function getEntitySiteUrls(FieldableEntityInterface $entity, $scheme = 'https') {
    $site_urls = [];

    $sites = $this->getEntitySites($entity, TRUE);
    if (!empty($sites['ids'])) {
      foreach ($sites['ids'] as $site_id) {
        $site = $this->getSiteById($site_id);
        if ($site) {
          $site_base_url = $this->getSiteBaseUrl($site, $scheme);
          if ($site_base_url) {
            // Return the absolute URL.
            $url = $entity->toUrl('canonical', [
              'absolute' => TRUE,
              'base_url' => $site_base_url,
            ])->toString();

            $site_urls[$site_id] = $url;
          }
        }
      }
    }

    return $site_urls;
  }

  /**
   * Returns all Site Base URLs of an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $scheme
   *   Default to https.
   *
   * @return string[]
   *   The list of base URLs.
   */
  public function getEntitySiteBaseUrls(FieldableEntityInterface $entity, $scheme = 'https') {
    $site_base_urls = [];

    $sites = $this->getEntitySites($entity, TRUE);
    if (!empty($sites['ids'])) {
      foreach ($sites['ids'] as $site_id) {
        $site = $this->getSiteById($site_id);
        if ($site) {
          $site_base_url = $this->getSiteBaseUrl($site, $scheme);
          if ($site_base_url) {
            $site_base_urls[$site_id] = $site_base_url;
          }
        }
      }
    }

    return $site_base_urls;
  }

  /**
   * Override a URL with a Site Base URL.
   *
   * @param string $url
   *   The URL, eg. http://content.vic.gov.au/about-us.
   * @param string $site_base_url
   *   The Site Base URL, eg. http://demo.vic.gov.au.
   *
   * @return string
   *   The overridden URL, eg. http://demo.vic.gov.au/about-us.
   */
  public function overrideUrlStringWithSiteBaseUrl($url, $site_base_url) {
    $url_components = parse_url($url);
    if ($url_components) {
      $original_base_url = $url_components['scheme'] . '://' . $url_components['host'];
      return str_replace($original_base_url, $site_base_url, $url);
    }

    return $url;
  }

  /**
   * Override a URL with base URL of a Site.
   *
   * @param string $url
   *   The URL, eg. https://content.vic.gov.au/about-us.
   * @param \Drupal\taxonomy\TermInterface $site
   *   The Site with a base URL, eg. https://demo.vic.gov.au.
   * @param string $scheme
   *   Default to https when retrieving site base URL.
   *
   * @return string
   *   The overridden URL, eg. https://demo.vic.gov.au/about-us.
   */
  public function overrideUrlStringWithSite($url, TermInterface $site, $scheme = 'https') {
    $site_base_url = $this->getSiteBaseUrl($site, $scheme);
    if ($site_base_url) {
      $url = $this->overrideUrlStringWithSiteBaseUrl($url, $site_base_url);
    }

    return $url;
  }

  /**
   * Returns the requested cache bin.
   *
   * @param string $bin
   *   (optional) The cache bin for which the cache object should be returned,
   *   defaults to 'default'.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache object associated with the specified bin.
   */
  protected function cache($bin = 'default') {
    return $this->container->get('cache.' . $bin);
  }

  /**
   * Return the cache ID for an entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The cache ID.
   */
  public function getSitesCacheId(FieldableEntityInterface $entity) {
    $cid = 'tide_site:entity:' . $entity->getEntityTypeId() . ':' . $entity->id();
    if ($entity instanceof RevisionableInterface) {
      $cid .= ':' . $entity->getRevisionId();
    }
    $cid .= ':sites';

    return $cid;
  }

  /**
   * Return the cache ID for GetRoute.
   *
   * @param string $path
   *   The requested path.
   * @param int $site_id
   *   The site ID.
   *
   * @return string
   *   The cache ID.
   */
  public function getRouteCacheId($path, $site_id) {
    $cid = 'tide_site:api:route:path:' . substr($path, 0, 128) . hash('sha256', $path) . ':site:' . $site_id;
    return $cid;
  }

}
