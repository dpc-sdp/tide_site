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
   * Alter system.admin_content route to the view '/summary_contents'.
   *
   * {@inheritDoc}.
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Comment it out temporarily for debugging why does
    // the view.summary_contents.page load pretty slow.
    /*
    $route = $collection->get('system.admin_content');
    $summary_contents_route = $collection->get('view.summary_contents.page');
    if ($route && $summary_contents_route) {
    $collection->add('system.admin_content', clone $summary_contents_route);
    }
     */
  }

}
