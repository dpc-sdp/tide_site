<?php

namespace Drupal\tide_site_simple_sitemap\Plugin\simple_sitemap\UrlGenerator;

use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;

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
            // Tide.
            if ($site_id = $this->getSiteIdFromPluginId()) {
              $query->condition('field_node_site.entity.tid', $site_id);
            }
            // Tide.
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
   * Gets site id from the plugin.
   */
  protected function getSiteIdFromPluginId() {
    $sites = \Drupal::service('tide_site.helper')->getAllSites();
    $ex = explode('-', $this->sitemap->id());
    $site_id = end($ex);
    if (is_numeric($site_id) && array_key_exists($site_id, $sites)) {
      return $site_id;
    }
    return NULL;
  }

  /**
   * Gets frontend url.
   */
  protected function getFrontendUrl($be_url) {
    $helper = \Drupal::service('tide_site.helper');
    $parsed_url = parse_url($be_url);
    $site_id = $this->getSiteIdFromPluginId();
    if ($helper->hasSitePrefix($parsed_url['path']) && $site_id) {
      $site_term = Term::load($site_id);
      $site_base_url = $helper->getSiteBaseUrl($site_term);
      $url = $helper->overrideUrlStringWithSite($be_url, $site_term);
      return \Drupal::service('tide_site.alias_storage_helper')->getPathAliasWithoutSitePrefix(['alias' => $url], $site_base_url);
    }
    return NULL;
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
        'url' => $this->getSiteIdFromPluginId() === NULL ? $url : $this->getFrontendUrl($url),
        'alternate_urls' => $alternate_urls,
      ];
    }

    return $url_variants;
  }

}
