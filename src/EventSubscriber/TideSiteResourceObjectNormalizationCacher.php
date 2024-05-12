<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher;
use Drupal\jsonapi\JsonApiResource\ResourceObject;

/**
 * Caches entity normalizations after the response has been sent.
 */
class TideSiteResourceObjectNormalizationCacher extends ResourceObjectNormalizationCacher {

  /**
   * Writes a normalization to cache.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
   *   The resource object for which to generate a cache item.
   * @param array $normalization_parts
   *   The normalization parts to cache.
   */
  protected function set(ResourceObject $object, array $normalization_parts) {
    // @todo Investigate whether to cache POST and PATCH requests.
    // @todo Follow up on https://www.drupal.org/project/drupal/issues/3381898.
    if (!$this->requestStack
      ->getCurrentRequest()
      ->isMethodCacheable()) {
      return;
    }

    // Merge the entity's cacheability metadata with that of the normalization
    // parts, so that VariationCache can take care of cache redirects for us.
    $cacheability = CacheableMetadata::createFromObject($object)
      // Adds url.query_args:site to the normalization.
      ->addCacheContexts(['url.query_args:site'])
      ->merge(static::mergeCacheableDependencies($normalization_parts[static::RESOURCE_CACHE_SUBSET_BASE]))
      ->merge(static::mergeCacheableDependencies($normalization_parts[static::RESOURCE_CACHE_SUBSET_FIELDS]));
      $this->variationCache
      ->set($this->generateCacheKeys($object), $normalization_parts, $cacheability, new CacheableMetadata());
  }

  /**
   * Generates the cache keys for a normalization.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
   *   The resource object for which to generate the cache keys.
   *
   * @return string[]
   *   The cache keys to pass to the variation cache.
   *
   * @see \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber::$dynamicPageCacheRedirectRenderArray
   */
  protected static function generateCacheKeys(ResourceObject $object) {
    return [
      $object->getResourceType()
        ->getTypeName(),
      $object->getId(),
      $object->getLanguage()
        ->getId(),
    ];
  }

}
