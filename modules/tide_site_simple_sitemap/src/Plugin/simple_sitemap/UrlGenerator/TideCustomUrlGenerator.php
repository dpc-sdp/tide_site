<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Plugin\simple_sitemap\SimpleSitemapPluginBase;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\CustomUrlGenerator;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;
use Drupal\tide_site_simple_sitemap\Plugin\Handler\TideSitemapHandlerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the custom URL generator.
 *
 * @UrlGenerator(
 *   id = "tide_custom",
 *   label = @Translation("Tide Custom URL generator"),
 *   description = @Translation("Tide Generates URLs."),
 * )
 */
class TideCustomUrlGenerator extends CustomUrlGenerator {

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
    $custom_links,
    $path_validator,
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
      $custom_links,
      $path_validator
    );
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
      $container->get('simple_sitemap.custom_link_manager'),
      $container->get('path.validator'),
      $container->get('tide_site.helper'),
      $container->get('tide_site.alias_storage_helper')
    );
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
    $site_base_url = $this->getSiteBaseUrl();
    foreach ($alternate_urls as $langcode => $url) {
      $url_variants[] = $path_data + [
        'langcode' => $langcode,
        'url' => empty($site_base_url) ? $url : $site_base_url,
        'alternate_urls' => $alternate_urls,
      ];
    }

    return $url_variants;
  }

}
