<?php

namespace Drupal\tide_site;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TideSiteServiceProvider.
 *
 * @package Drupal\tide_site
 */
class TideSiteServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides path_alias.manager class to add site path prefix.
    $alias_manager_definition = $container->getDefinition('path_alias.manager');
    $alias_manager_definition->setClass('Drupal\tide_site\AliasManager')
      ->addArgument(new Reference('tide_site.alias_storage_helper'));

    // Overrides linkit.suggestion_manager service (Linkit 5.x).
    if ($container->hasDefinition('linkit.suggestion_manager')) {
      $linkit_definition = $container->getDefinition('linkit.suggestion_manager');
      $linkit_definition->setClass('Drupal\tide_site\LinkitResultManager');
      $linkit_definition->setArguments([
        new Reference('tide_site.alias_storage'),
        new Reference('tide_site.alias_storage_helper'),
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // Dynamically define the service tide_site.get_route_subscriber.
    $modules = $container->getParameter('container.modules');

    // Check for installed tide_api module.
    if (isset($modules['tide_api'])) {
      $container->register('tide_site.get_route_subscriber', 'Drupal\tide_site\EventSubscriber\TideSiteGetRouteSubscriber')
        ->addTag('event_subscriber')
        ->setArguments([
          new Reference('tide_site.helper'),
          new Reference('tide_api.helper'),
        ])
        ->addMethodCall('setContainer', [new Reference('service_container')])
        ->addMethodCall('setStringTranslation', [new Reference('string_translation')]);
    }

    if (isset($modules['tide_api'])) {
      $container->register('tide_site.get_cache_id_subscriber', 'Drupal\tide_site\EventSubscriber\TideSiteGetCacheIdSubscriber')
        ->addTag('event_subscriber');
    }

    if (isset($modules['jsonapi']) && isset($modules['jsonapi_extras'])) {
      $container->register('tide_site.request_event_subscriber', 'Drupal\tide_site\EventSubscriber\TideSiteRequestEventSubscriber')
        ->addTag('event_subscriber')
        ->setArguments([
          new Reference('module_handler'),
          new Reference('tide_site.helper'),
        ])
        ->addMethodCall('setContainer', [new Reference('service_container')])
        ->addMethodCall('setStringTranslation', [new Reference('string_translation')]);
    }
  }

}
