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
    $sitemap_generator_definition = $container->getDefinition('simple_sitemap.sitemap_generator');
    $sitemap_generator_definition->setClass('Drupal\tide_site_simple_sitemap\SitemapGenerator')
      ->addMethodCall('setHelper', [new Reference('tide_site.helper')])
      ->addMethodCall('setAliasHelper', [new Reference('tide_site.alias_storage_helper')])
      ->addMethodCall('setEntityTypeManager', [new Reference('entity_type.manager')])
      ->addMethodCall('setRequest', [new Reference('request_stack')]);

    $sitemap_definition = $container->getDefinition('simple_sitemap.generator');
    $sitemap_definition->setClass('Drupal\tide_site_simple_sitemap\Simplesitemap')
      ->addMethodCall('setRequest', [new Reference('request_stack')]);
  }

}
