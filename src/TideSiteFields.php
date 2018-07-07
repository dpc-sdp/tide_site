<?php

namespace Drupal\tide_site;

use Drupal\field\Entity\FieldConfig;
use Drupal\tide_core\TideCoreFields;

/**
 * Class TideSiteFields.
 *
 * @package Drupal\tide_site.
 */
class TideSiteFields extends TideCoreFields {

  /**
   * Site field machine name.
   */
  const FIELD_SITE = 'field_ENTITY_TYPE_site';

  /**
   * Primary site field machine name.
   */
  const FIELD_PRIMARY_SITE = 'field_ENTITY_TYPE_primary_site';

  /**
   * Helper to check if fields name is one of the 'site' fields.
   *
   * @param string $field_name
   *   Field name.
   * @param string $field_name_generic
   *   Optional generic field name. When provided, fields are also checked for
   *   compliance with provided generic field.
   *
   * @return bool
   *   TRUE if field matches, FALSE otherwise.
   */
  public static function isSiteField($field_name, $field_name_generic = NULL) {
    $map = [
      self::FIELD_SITE,
      self::FIELD_PRIMARY_SITE,
    ];

    // Sort map by length, allowing to more specific fields to match first.
    usort($map, function ($a, $b) {
      return strlen($b) - strlen($a);
    });

    $generic_matched_prev = FALSE;
    foreach ($map as $field_name_map) {
      $pattern = '/^' . self::normaliseFieldName($field_name_map, '.*', '.*') . '$/';
      $matched = (bool) preg_match($pattern, $field_name);
      if ($matched) {
        // Match also on generic name, if provided.
        if ($field_name_generic) {
          $generic_matched = $field_name_map == $field_name_generic;
          // Stop only if matched on generic name and not matched before.
          if ($matched && $generic_matched && !$generic_matched_prev) {
            break;
          }
          $generic_matched_prev = $matched;
          $matched = FALSE;
        }
        else {
          break;
        }
      }
    }

    return $matched;
  }

  /**
   * Helper to convert field name to machine name.
   */
  public static function normaliseFieldName($field_name, $entity_type_id = '', $bundle = '') {
    $field_name = str_replace('ENTITY_TYPE', $entity_type_id, $field_name);
    $field_name = str_replace('BUNDLE', $bundle, $field_name);
    $field_name = str_replace('__', '_', $field_name);

    return $field_name;
  }

  /**
   * Config for 'Site' filed.
   */
  protected function getFieldSiteConfig() {
    return [
      'field_name' => self::FIELD_SITE,
      'label' => 'Site',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'sites' => 'sites',
          ],
        ],
      ],
      'required' => TRUE,
    ];
  }

  /**
   * Config for 'Site' filed on media entity type.
   */
  protected function getFieldMediaSiteConfig() {
    return ['required' => FALSE] + $this->getFieldSiteConfig();
  }

  /**
   * Config for 'Site' filed form display components.
   */
  protected function getFieldSiteFormDisplayComponents() {
    return [
      'type' => 'options_buttons',
      'region' => 'content',
    ];
  }

  /**
   * Config for 'Primary site' filed.
   */
  protected function getFieldPrimarySiteConfig() {
    return [
      'field_name' => self::FIELD_PRIMARY_SITE,
      'label' => 'Primary Site',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => [
            'sites' => 'sites',
          ],
        ],
      ],
      'required' => TRUE,
    ];
  }

  /**
   * Config for 'Primary site' filed form display components.
   */
  protected function getFieldPrimarySiteFormDisplayComponents() {
    return [
      'type' => 'options_buttons',
      'region' => 'content',
    ];
  }

  /**
   * Add a content type to the field_site_homepage field of Sites taxonomy.
   *
   * @param string[] $bundles
   *   Content types.
   */
  public function addContentTypesToSiteHomepageField(array $bundles) {
    try {
      $fields = $this->entityTypeManager
        ->getStorage('field_config')
        ->loadByProperties([
          'field_name' => 'field_site_homepage',
          'entity_type' => 'taxonomy_term',
        ]);
      if ($fields) {
        $field = reset($fields);
        $field_config = $field->toArray();
        foreach ($bundles as $bundle) {
          if (empty($field_config['settings']['handler_settings']['target_bundles'][$bundle])) {
            $field_config['settings']['handler_settings']['target_bundles'][$bundle] = $bundle;
          }
        }
        $new_field = FieldConfig::create($field_config);
        $new_field->setOriginalId($field->id());
        $new_field->enforceIsNew(FALSE);
        $new_field->save();
      }
    }
    catch (\Exception $exception) {
      watchdog_exception('tide_site', $exception);
    }
  }

}
