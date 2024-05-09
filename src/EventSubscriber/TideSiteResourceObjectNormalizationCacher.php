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
   * @see \Drupal\jsonapi\EventSubscriber\ResourceObjectNormalizationCacher
   */
  protected function set(ResourceObject $object, array $normalization_parts) {
    $base = static::generateLookupRenderArray($object);
    $data_as_render_array = $base + [
        // The data we actually care about.
      '#data' => $normalization_parts,
        // Tell RenderCache to cache the #data property: the data we actually
        // care about.
      '#cache_properties' => ['#data'],
        // These exist only to fulfill the requirements of the RenderCache,
        // which is designed to work with render arrays only. We don't care
        // about these.
      '#markup' => '',
      '#attached' => '',
    ];

    // Merge the entity's cacheability metadata with that of the normalization
    // parts, so that RenderCache can take care of cache redirects for us.
    CacheableMetadata::createFromObject($object)
      // Adds url.query_args:site to the normalization.
      ->addCacheContexts(['url.query_args:site'])
      ->merge(static::mergeCacheableDependencies($normalization_parts[static::RESOURCE_CACHE_SUBSET_BASE]))
      ->merge(static::mergeCacheableDependencies($normalization_parts[static::RESOURCE_CACHE_SUBSET_FIELDS]))
      ->applyTo($data_as_render_array);

    $this->renderCache->set($data_as_render_array, $base);
  }
/**
* Generates a lookup render array for a normalization.
*
* @param \Drupal\jsonapi\JsonApiResource\ResourceObject $object
*   The resource object for which to generate a cache item.
*
* @return array
*   A render array for use with the RenderCache service.
*
* @see \Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber::$dynamicPageCacheRedirectRenderArray
*/
  protected static function generateLookupRenderArray(ResourceObject $object) {
    return [
        '#cache' => [
            'keys' => [
                $object->getResourceType()
                    ->getTypeName(),
                $object->getId(),
            ],
            'bin' => 'jsonapi_normalizations',
        ],
    ];
  }

}
