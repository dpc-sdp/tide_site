<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class TideSiteRedirection.
 *
 * @package Drupal\tide_site\RouteSubscriber
 */
class TideSiteRouteAlter extends RouteSubscriberBase {

  /**
   * Alter system.admin_content route to the view '/summary_contents'.
   *
   * {@inheritDoc}.
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.admin_content')) {
      /** @var \Drupal\Core\Routing\RouteProvider $route_provider */
      $route_provider = \Drupal::service('router.route_provider');
      if (count($route_provider->getRoutesByNames(['view.summary_contents.page'])) === 1) {
        $collection->add('system.admin_content', clone $collection->get('view.summary_contents.page'));
      }
    }
  }

}
