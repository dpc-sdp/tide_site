<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tide_api\Event\GetRouteEvent;
use Drupal\tide_api\TideApiEvents;
use Drupal\tide_api\TideApiHelper;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class get route subscriber for tide_site.
 *
 * @package Drupal\tide_site\EventSubscriber
 */
class TideSiteGetRouteSubscriber implements EventSubscriberInterface {
  use ContainerAwareTrait;
  use StringTranslationTrait;

  /**
   * Tide Site Helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $siteHelper;

  /**
   * Tide Api Helper.
   *
   * @var \Drupal\tide_api\TideApiHelper
   */
  protected $apiHelper;

  /**
   * TideSiteGetRouteSubscriber constructor.
   *
   * @param \Drupal\tide_site\TideSiteHelper $site_helper
   *   Tide Site Helper.
   * @param \Drupal\tide_api\TideApiHelper $api_helper
   *   Tide API Helper.
   */
  public function __construct(TideSiteHelper $site_helper, TideApiHelper $api_helper) {
    $this->siteHelper = $site_helper;
    $this->apiHelper = $api_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    $events[TideApiEvents::GET_ROUTE][] = ['onApiGetRouteAddSiteFilter', -10];

    return $events;
  }

  /**
   * Adds Site filter to Tide API router.
   *
   * @param \Drupal\tide_api\Event\GetRouteEvent $event
   *   The event.
   */
  public function onApiGetRouteAddSiteFilter(GetRouteEvent $event) {
    // Only process if the status code is not 400 Bad Request.
    if ($event->isBadRequest()) {
      return;
    }

    $request = $event->getRequest();
    $path = $this->apiHelper->getRequestedPath($request);

    $response = $event->getJsonResponse();
    if (empty($response['data']) && $path !== '/') {
      return;
    }

    try {
      $uuid = $response['data']['id'] ?? NULL;

      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $event->getEntity();
      if ($entity && $entity->getEntityTypeId() == 'redirect') {
        $this->processGetRouteRedirect($event);
        return;
      }

      $entity_type = $response['data']['attributes']['entity_type'] ?? NULL;
      // Do nothing if this is not a restricted entity type.
      if (!$this->siteHelper->isRestrictedEntityType($entity_type) && $path !== '/') {
        return;
      }

      $site_id = $request->query->get('site');
      // No Site ID provided, should we return a 400 status code?
      if (empty($site_id)) {
        // Fetch the entity.
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
        // The Entity maybe empty as TideApi loaded its route data from cache.
        if (!$entity) {
          $entity = $this->siteHelper->getEntityByUuid($uuid, $entity_type);
        }
        if ($entity && $this->siteHelper->isRestrictedEntityType($entity->getEntityTypeId())) {
          $sites = $this->siteHelper->getEntitySites($entity);
          // This entity has Sites and is restricted from being accessed by Site
          // but our required Site parameter is missing,
          // so we stop processing and return a Bad Request 400 code.
          if ($sites) {
            $event->setCode(Response::HTTP_BAD_REQUEST);
            $this->apiHelper->setJsonResponseError($response, $event->getCode(), $this->t("URL query parameter 'site' is required."));
          }
        }
      }
      // Fetch the entity and validate its Site.
      else {
        // Attempt to load the response from data cache.
        $cid = $this->siteHelper->getRouteCacheId($path, $site_id);
        $cache_response = $this->cache('data')->get($cid);
        if ($cache_response) {
          $event->setCode($cache_response->data['code']);
          $response = $cache_response->data['response'];
          if (!empty($cache_response->tags) && is_array($cache_response->tags)) {
            $event->getCacheableMetadata()->addCacheTags($cache_response->tags);
          }
        }
        // Cache miss.
        else {
          // Check if the requested path is homepage.
          if ($path == '/') {
            // Ignore the current response
            // because each site has its own homepage.
            $site_term = $this->siteHelper->getSiteById($site_id);
            $entity = $this->siteHelper->getSiteHomepageEntity($site_term);

            // The site does not have a homepage,
            // load the global frontpage instead.
            if (!$entity) {
              $frontpage = $this->apiHelper->getFrontPagePath();
              $frontpage_url = $this->apiHelper->findUrlFromPath($frontpage);
              if ($frontpage_url) {
                $entity = $this->apiHelper->findEntityFromUrl($frontpage_url);
              }
            }

            // Now we have the homepage entity, override response data.
            if ($entity) {
              // Override response data with site homepage.
              $this->apiHelper->setJsonResponseDataAttributesFromEntity($response, $entity, $event->getCacheableMetadata());
            }
          }
          // Not homepage, fetch the entity from the response.
          else {
            $entity = $event->getEntity();
            // The Entity maybe empty as TideApi loaded its data from cache.
            if (!$entity) {
              $entity = $this->siteHelper->getEntityByUuid($uuid, $entity_type);
            }
          }

          // The entity is missing for some reasons.
          if (!$entity) {
            $event->setCode(Response::HTTP_NOT_FOUND);
            $this->apiHelper->setJsonResponseError($response, $event->getCode());
          }
          // Now we have the entity, check if its Site ID matches the request.
          // Again, only works with restricted entity types.
          elseif ($this->siteHelper->isRestrictedEntityType($entity->getEntityTypeId())) {
            $cache_tags = [$site_id => 'taxonomy_term:' . $site_id];
            $valid = $this->siteHelper->isEntityBelongToSite($entity, $site_id);
            // It belongs to the right Site.
            if ($valid) {
              $sites = $this->siteHelper->getEntitySites($entity);
              // Add Section ID to the response.
              $section_id = $sites['sections'][$site_id];
              $response['data']['attributes']['section'] = $section_id;
              $cache_tags[$section_id] = 'taxonomy_term:' . $section_id;
              unset($response['errors']);
              $event->setCode(Response::HTTP_OK);
            }
            // The entity does not belong to the requested Site.
            else {
              $event->setCode(Response::HTTP_NOT_FOUND);
              $this->apiHelper->setJsonResponseError($response, Response::HTTP_NOT_FOUND);
            }

            $this->cache('data')->set($cid, [
              'code' => $event->getCode(),
              'response' => $response,
            ], Cache::PERMANENT, Cache::mergeTags($entity->getCacheTags(), array_values($cache_tags)));
          }
        }
      }

      // Update the altered response.
      $event->setJsonResponse($response);
    }
    catch (\Exception $e) {
      // Does nothing.
    }

    // The API call does not pass Site filter, stop propagating the event.
    if (!$event->isOk()) {
      $event->stopPropagation();
    }
  }

  /**
   * Process the redirect from the GetRoute event.
   *
   * @param \Drupal\tide_api\Event\GetRouteEvent $event
   *   The event object.
   */
  protected function processGetRouteRedirect(GetRouteEvent $event) {
    $response = $event->getJsonResponse();
    // Only process the redirect to internal paths.
    if ($response['data']['attributes']['redirect_type'] != 'internal') {
      return;
    }

    $redirect_url = $response['data']['attributes']['redirect_url'];
    $prefix_site_id = $this->siteHelper->getSiteIdFromSitePrefix($redirect_url);
    // This destination path does not have the site prefix, bail out early.
    if (!$prefix_site_id) {
      return;
    }

    $destination_site = $this->siteHelper->getSiteById($prefix_site_id);
    if ($destination_site) {
      $prefix = $this->siteHelper->getSitePathPrefix($destination_site);
      $current_site_id = $event->getRequest()->query->get('site');
      // Remove the site prefix and returns if the redirect belongs to the
      // same site.
      $redirect_url = str_replace($prefix, '', $redirect_url);
      if ($current_site_id == $destination_site->id()) {
        $response['data']['attributes']['redirect_url'] = $redirect_url;
      }
      // Otherwise treat it as an external path, and return the full URL
      // of the destination site.
      else {
        $response['data']['attributes']['redirect_type'] = 'external';
        $response['data']['attributes']['redirect_url'] = $this->siteHelper->getSiteBaseUrl($destination_site) . $redirect_url;
      }
      $event->setJsonResponse($response);
      $event->getCacheableMetadata()->addCacheableDependency($destination_site);
    }
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

}
