<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tide_api\Event\GetRouteEvent;
use Drupal\tide_api\TideApiEvents;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TideSiteGetRouteSubscriber.
 *
 * @package Drupal\tide_site\EventSubscriber
 */
class TideSiteGetRouteSubscriber implements EventSubscriberInterface {
  use ContainerAwareTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
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
    $path = $request->query->get('path');

    $response = $event->getJsonResponse();
    if (empty($response['data']) && $path !== '/') {
      return;
    }

    /** @var \Drupal\tide_site\TideSiteHelper $helper */
    $helper = $this->container->get('tide_site.helper');
    /** @var \Drupal\tide_api\TideApiHelper $api_helper */
    $api_helper = $this->container->get('tide_api.helper');

    try {
      $uuid = isset($response['data']['uuid']) ? $response['data']['uuid'] : NULL;

      $entity_type = isset($response['data']['entity_type']) ? $response['data']['entity_type'] : NULL;
      // Do nothing if this is not a restricted entity type.
      if (!$helper->isRestrictedEntityType($entity_type) && $path !== '/') {
        return;
      }

      $site_id = $request->query->get('site');
      // No Site ID provided, should we return a 400 status code?
      if (empty($site_id)) {
        // Fetch the entity.
        $entity = $event->getEntity();
        // The Entity maybe empty as TideApi loaded its route data from cache.
        if (!$entity) {
          $entity = $helper->getEntityByUuid($uuid, $entity_type);
        }
        if ($entity && $helper->isRestrictedEntityType($entity->getEntityTypeId())) {
          $sites = $helper->getEntitySites($entity);
          // This entity has Sites and is restricted from being accessed by Site
          // but our required Site parameter is missing,
          // so we stop processing and return a Bad Request 400 code.
          if ($sites) {
            $event->setCode(Response::HTTP_BAD_REQUEST);
            $response['errors'] = [$this->t('URL query parameter "site" is required.')];
          }
        }
      }
      // Fetch the entity and validate its Site.
      else {
        // Attempt to load the response from data cache.
        $cid = 'tide_site:api:route:path:' . hash('sha256', $path) . ':site:' . $site_id;
        $cache_response = $this->cache('data')->get($cid);
        if ($cache_response) {
          $event->setCode($cache_response->data['code']);
          $response = $cache_response->data['response'];
        }
        // Cache miss.
        else {
          // Check if the requested path is homepage.
          if ($path == '/') {
            // Ignore the current response
            // because each site has its own homepage.
            $site_term = $helper->getSiteById($site_id);
            $entity = $helper->getSiteHomepage($site_term);

            // The site does not have a homepage,
            // load the global frontpage instead.
            if (!$entity) {
              $frontpage = $api_helper->getFrontPagePath();
              $frontpage_url = $api_helper->findUrlFromPath($frontpage);
              if ($frontpage_url) {
                $entity = $api_helper->findEntityFromUrl($frontpage_url);
              }
            }

            // Now we have the homepage entity, override response data.
            if ($entity) {
              // Override response data with site homepage.
              $endpoint = $api_helper->findEndpointFromEntity($entity);
              $entity_type = $entity->getEntityTypeId();
              $response['data'] = [
                'entity_type' => $entity_type,
                'entity_id' => $entity->id(),
                'bundle' => $entity->bundle(),
                'uuid' => $entity->uuid(),
                'endpoint' => $endpoint,
              ];
            }
          }
          // Not homepage, fetch the entity from the response.
          else {
            $entity = $event->getEntity();
            // The Entity maybe empty as TideApi loaded its data from cache.
            if (!$entity) {
              $entity = $helper->getEntityByUuid($uuid, $entity_type);
            }
          }

          // The entity is missing for some reasons.
          if (!$entity) {
            $event->setCode(Response::HTTP_NOT_FOUND);
            $response['errors'] = [$this->t('Path not found.')];
          }
          // Now we have the entity, check if its Site ID matches the request.
          // Again, only works with restricted entity types.
          elseif ($helper->isRestrictedEntityType($entity->getEntityTypeId())) {
            $cache_tags = [$site_id => 'taxonomy_term:' . $site_id];
            $valid = $helper->isEntityBelongToSite($entity, $site_id);
            // It belongs to the right Site.
            if ($valid) {
              $sites = $helper->getEntitySites($entity);
              // Add Section ID to the response.
              $section_id = $sites['sections'][$site_id];
              $response['data']['section'] = $section_id;
              $cache_tags[$section_id] = 'taxonomy_term:' . $section_id;
              unset($response['errors']);
              $event->setCode(Response::HTTP_OK);
            }
            // The entity does not belong to the requested Site.
            else {
              $event->setCode(Response::HTTP_NOT_FOUND);
              $response['errors'] = [$this->t('Path not found.')];
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
