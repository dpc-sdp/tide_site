<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\SitemapGenerator;

use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SitemapGenerator\DefaultSitemapGenerator;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;
use Drupal\tide_site_simple_sitemap\Plugin\Handler\TideSitemapHandlerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class default sitemap generator.
 *
 * @SitemapGenerator(
 *   id = "tide_default",
 *   label = @Translation("Tide Default sitemap generator"),
 *   description = @Translation("Generates a standard conform hreflang sitemap
 *   of your content."),
 * )
 */
class TideDefaultSitemapGenerator extends DefaultSitemapGenerator {

  use TideSitemapHandlerTrait;

  /**
   * Tide site helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $siteHelper;

  /**
   * Tide alias helper.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $aliasStorageHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $module_handler,
    $sitemap_writer,
    $settings,
    $module_list,
    TideSiteHelper $siteHelper,
    AliasStorageHelper $aliasStorageHelper
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $module_handler, $sitemap_writer, $settings, $module_list);
    $this->siteHelper = $siteHelper;
    $this->aliasStorageHelper = $aliasStorageHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler'),
      $container->get('simple_sitemap.sitemap_writer'),
      $container->get('simple_sitemap.settings'),
      $container->get('extension.list.module'),
      $container->get('tide_site.helper'),
      $container->get('tide_site.alias_storage_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIndexContent(): string {
    $this->writer->openMemory();
    $this->writer->setIndent(TRUE);
    $this->writer->startSitemapDocument();

    $this->addXslUrl();
    $this->writer->writeGeneratedBy();
    $this->writer->startElement('sitemapindex');

    // Add attributes to document.
    $attributes = self::$indexAttributes;
    $this->moduleHandler->alter('simple_sitemap_index_attributes', $attributes, $this->sitemap);
    foreach ($attributes as $name => $value) {
      $this->writer->writeAttribute($name, $value);
    }
    $site_base_url = $this->getSiteBaseUrl();
    // Add sitemap chunk locations to document.
    for ($delta = 1; $delta <= $this->sitemap->fromUnpublished()->getChunkCount(); $delta++) {
      $this->writer->startElement('sitemap');
      if (empty($site_base_url)) {
        $this->writer->writeElement('loc', $this->sitemap->toUrl('canonical', ['delta' => $delta])->toString());
      }
      else {
        $this->writer->writeElement('loc', $site_base_url . '/sitemap.xml?page=' . $delta);
      }
      $this->writer->writeElement('lastmod', date('c', $this->sitemap->fromUnpublished()->getCreated()));
      $this->writer->endElement();
    }

    $this->writer->endElement();
    $this->writer->endDocument();

    return $this->writer->outputMemory();
  }

}
