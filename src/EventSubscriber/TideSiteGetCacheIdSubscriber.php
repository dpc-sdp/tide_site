<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\tide_api\Event\GetCacheIdEvent;
use Drupal\tide_api\TideApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class TideSiteGetRouteSubscriber.
 *
 * @package Drupal\tide_site\EventSubscriber
 */
class TideSiteGetCacheIdSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[TideApiEvents::GET_CACHE_ID][] = ['onApiGetCacheId'];
    return $events;
  }

  /**
   * Alter cache id if necessary.
   *
   * @param \Drupal\tide_api\Event\GetCacheIdEvent $event
   *   The GetCacheIdEvent event.
   */
  public function onApiGetCacheId(GetCacheIdEvent $event) {
    $request = $event->getRequest();
    $site_id = $request->query->get('site');
    $cid = $event->getCacheId();
    // If there was no :site: been added, we add site id to the end of cid.
    if (strpos($cid, ':site:') === FALSE && !empty($site_id)) {
      $event->setCacheId($cid . ':site:' . $site_id);
    }
    else {
      // Checking if at the end of string is a number.
      if (is_numeric(substr($cid, -1, 1))) {
        // Checking if the site id in the cid is not equal to site id
        // that is queried.
        preg_match('/:site:\s*(\d+)/', $cid, $matches);
        if (!empty($matches) && $matches[1] != $site_id) {
          // Replace site id.
          $new_cid = preg_replace("/site:(\d+)$/", "site:" . $site_id, $cid);
          $event->setCacheId($new_cid);
        }
      }
    }
  }

}
