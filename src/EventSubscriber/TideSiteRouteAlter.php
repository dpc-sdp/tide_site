<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class TideSiteRouteAlter.
 *
 * @package Drupal\tide_site
 */
class TideSiteRouteAlter extends RouteSubscriberBase {

  /**
   * Alter system.admin_content route to the view '/summary_contents_filters'.
   *
   * {@inheritDoc}.
   */
  protected function alterRoutes(RouteCollection $collection) {
    $route = $collection->get('system.admin_content');
    $summary_contents_route = $collection->get('view.summary_contents_filters.page_1');
    if ($route && $summary_contents_route) {
      $collection->add('system.admin_content', clone $summary_contents_route);
    }
  }

}
