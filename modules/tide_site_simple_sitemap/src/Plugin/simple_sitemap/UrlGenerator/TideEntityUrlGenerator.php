<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;
use Drupal\tide_site_simple_sitemap\Plugin\Handler\TideSitemapHandlerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class entity url generator.
 *
 * @UrlGenerator(
 *   id = "tide_entity",
 *   label = @Translation("Tide Entity URL generator"),
 *   description = @Translation("Generates URLs for entity bundles and bundle
 *   overrides."),
 * )
 */
class TideEntityUrlGenerator extends EntityUrlGenerator {

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
    $logger,
    $settings,
    $language_manager,
    $entity_type_manager,
    $entity_helper,
    $entities_manager,
    $url_generator_manager,
    $memory_cache,
    TideSiteHelper $siteHelper,
    AliasStorageHelper $aliasStorageHelper
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger,
      $settings,
      $language_manager,
      $entity_type_manager,
      $entity_helper,
      $entities_manager,
      $url_generator_manager,
      $memory_cache
    );
    $this->entitiesPerDataset = $this->settings->get('entities_per_queue_item', 50);
    $this->siteHelper = $siteHelper;
    $this->aliasStorageHelper = $aliasStorageHelper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition): SimpleSitemapPluginBase {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('simple_sitemap.logger'),
      $container->get('simple_sitemap.settings'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.entity_helper'),
      $container->get('simple_sitemap.entity_manager'),
      $container->get('plugin.manager.simple_sitemap.url_generator'),
      $container->get('entity.memory_cache'),
      $container->get('tide_site.helper'),
      $container->get('tide_site.alias_storage_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDataSets(): array {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
    $all_bundle_settings = $this->entitiesManager->setVariants($this->sitemap->id())->getAllBundleSettings();
    if (isset($all_bundle_settings[$this->sitemap->id()])) {
      foreach ($all_bundle_settings[$this->sitemap->id()] as $entity_type_name => $bundles) {
        if (!isset($sitemap_entity_types[$entity_type_name])) {
          continue;
        }
        if ($this->isOverwrittenForEntityType($entity_type_name)) {
          continue;
        }
        $entityTypeStorage = $this->entityTypeManager->getStorage($entity_type_name);
        $keys = $sitemap_entity_types[$entity_type_name]->getKeys();

        foreach ($bundles as $bundle_name => $bundle_settings) {
          if ($bundle_settings['index']) {
            $query = $entityTypeStorage->getQuery();
            if ($site_id = $this->getSiteIdFromSitemapId()) {
              $query->condition('field_node_site.entity.tid', $site_id);
            }
            if (!empty($keys['id'])) {
              $query->sort($keys['id']);
            }
            if (!empty($keys['bundle'])) {
              $query->condition($keys['bundle'], $bundle_name);
            }
            if (!empty($keys['published'])) {
              $query->condition($keys['published'], 1);
            }
            elseif (!empty($keys['status'])) {
              $query->condition($keys['status'], 1);
            }

            $query->accessCheck(FALSE);

            $data_set = [
              'entity_type' => $entity_type_name,
              'id' => [],
            ];
            foreach ($query->execute() as $entity_id) {
              $data_set['id'][] = $entity_id;
              if (count($data_set['id']) >= $this->entitiesPerDataset) {
                $data_sets[] = $data_set;
                $data_set['id'] = [];
              }
            }
            // Add the last data set if there are some IDs gathered.
            if (!empty($data_set['id'])) {
              $data_sets[] = $data_set;
            }
          }
        }
      }
    }
    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function getUrlVariants(array $path_data, Url $url_object): array {
    $url_variants = [];

    if (!$this->sitemap->isMultilingual() || !$url_object->isRouted()) {

      // Not a routed URL or URL language negotiation disabled: Including only
      // default variant.
      $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
    }
    elseif ($this->settings->get('skip_untranslated')
      && ($entity = $this->entityHelper->getEntityFromUrlObject($url_object)) instanceof ContentEntityInterface) {

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $translation_languages = $entity->getTranslationLanguages();
      if (isset($translation_languages[LanguageInterface::LANGCODE_NOT_SPECIFIED])
        || isset($translation_languages[LanguageInterface::LANGCODE_NOT_APPLICABLE])) {

        // Content entity's language is unknown: Including only default variant.
        $alternate_urls = $this->getAlternateUrlsForDefaultLanguage($url_object);
      }
      else {
        // Including only translated variants of content entity.
        $alternate_urls = $this->getAlternateUrlsForTranslatedLanguages($entity, $url_object);
      }
    }
    else {
      // Not a content entity or including all untranslated variants.
      $alternate_urls = $this->getAlternateUrlsForAllLanguages($url_object);
    }

    foreach ($alternate_urls as $langcode => $url) {
      $url_variants[] = $path_data + [
        'langcode' => $langcode,
        'url' => $this->getSiteIdFromSitemapId() === NULL ? $url : $this->getFrontendUrl($url),
        'alternate_urls' => $alternate_urls,
      ];
    }

    return $url_variants;
  }

}
