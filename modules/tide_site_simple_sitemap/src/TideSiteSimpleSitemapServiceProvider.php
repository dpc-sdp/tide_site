<?php

namespace Drupal\tide_site_simple_sitemap;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class simple sitemap service provider for tide_site.
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
    $sitemap_manager = $container->getDefinition('simple_sitemap.manager');
    $sitemap_manager->setClass('Drupal\tide_site_simple_sitemap\SimplesitemapManager');
  }

}
