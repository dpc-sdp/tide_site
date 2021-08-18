<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TideSiteSimpleSitemapServiceProvider.
 *
 * @package Drupal\tide_site
 */
class TideSiteSimpleSitemapServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $sitemap_definition = $container->getDefinition('simple_sitemap.generator');
    $sitemap_definition->setClass('Drupal\tide_site_simple_sitemap\Simplesitemap')
      ->addMethodCall('setRequest', [new Reference('request_stack')]);
  }

}
