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
    // Overrides path.alias_storage class to add site path prefix.
    $alias_storage_definition = $container->getDefinition('path.alias_storage');
    $alias_storage_definition->setClass('Drupal\tide_site\AliasStorage');

    // Overrides path.alias_manager class to add site path prefix.
    $alias_manager_definition = $container->getDefinition('path.alias_manager');
    $alias_manager_definition->setClass('Drupal\tide_site\AliasManager')
      ->addArgument(new Reference('tide_site.alias_storage_helper'));

    // Overrides linkit.result_manager service (Linkit 4.x).
    if ($container->hasDefinition('linkit.result_manager')) {
      $linkit_definition = $container->getDefinition('linkit.result_manager');
      $linkit_definition->setClass('Drupal\tide_site\LinkitResultManager');
      $linkit_definition->setArguments([
        new Reference('tide_site.alias_storage'),
        new Reference('tide_site.alias_storage_helper'),
      ]);
    }
  }

}
