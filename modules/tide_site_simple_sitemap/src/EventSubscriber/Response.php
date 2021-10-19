<?php

namespace Drupal\tide_site_simple_sitemap\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class tide_site response.
 *
 * @package Drupal\tide_site_simple_sitemap\EventSubscriber
 */
class Response implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run before DynamicPageCacheSubscriber (priority 100).
    $events = [];
    $events[KernelEvents::RESPONSE][] = [
      'onSimpleSitemapResponseAddCacheContext',
      101,
    ];

    return $events;
  }

  /**
   * Add more cache context to the response of Simple Sitemap.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event.
   */
  public function onSimpleSitemapResponseAddCacheContext(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $request = $event->getRequest();
    $route = $request->attributes->get('_route');
    $simple_sitemap_routes = [
      'simple_sitemap.sitemap',
      'simple_sitemap.sitemaps',
      'simple_sitemap.chunk',
      'simple_sitemap.chunk_fallback',
    ];
    if (in_array($route, $simple_sitemap_routes)) {
      $context = $response->getCacheableMetadata()->getCacheContexts();
      $context = Cache::mergeContexts($context, ['url']);
      $response->getCacheableMetadata()->setCacheContexts($context);
    }
  }

}
