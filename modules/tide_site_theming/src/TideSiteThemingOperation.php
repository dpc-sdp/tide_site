<?php

namespace Drupal\tide_site_theming;

use Drupal\user\Entity\Role;

/**
 * Required changes to form and view display.
 */
class TideSiteThemingOperation {

  /**
   * Add necissary changes to the form and view display.
   */
  public function requiredChangesForTheming() {
    // Update entity form display.
    $entity_form_display = \Drupal::entityTypeManager()
      ->getStorage('entity_form_display')
      ->load('taxonomy_term.sites.default');
    if ($entity_form_display) {
      // Set field_site_theme_values.
      $entity_form_display->setComponent('field_site_theme_values', [
        'type' => 'key_value_textfield',
        'weight' => 18,
        'region' => 'content',
        'settings' => [
          'size' => 60,
          'placeholder' => '',
          'key_label' => 'Key',
          'value_label' => 'Value',
          'description_label' => 'Description',
          'description_rows' => 5,
          'key_size' => 60,
          'key_placeholder' => '',
          'description_enabled' => FALSE,
          'description_placeholder' => '',
        ],
        'third_party_settings' => [],
      ]);
      $field_group = $entity_form_display->getThirdPartySettings('field_group');
      $field_group['group_site_theme_values'] = [
        'children' => [
          'field_site_theme_values',
        ],
        'parent_name' => '',
        'label' => 'Site theme values',
        'weight' => 17,
        'format_type' => 'details',
        'region' => 'content',
        'format_settings' => [
          'classes' => '',
          'show_empty_fields' => FALSE,
          'id' => 'tide-site-theming-fileds',
          'open' => FALSE,
          'required_fields' => TRUE,
          'effect' => 'none',
        ],
      ];
      $entity_form_display->setThirdPartySetting('field_group', 'group_site_theme_values', $field_group['group_site_theme_values']);

      // Set field_site_feature_flags.
      $entity_form_display->setComponent('field_site_feature_flags', [
        'type' => 'key_value_textfield',
        'weight' => 19,
        'region' => 'content',
        'settings' => [
          'size' => 60,
          'placeholder' => '',
          'key_label' => 'Key',
          'value_label' => 'Value',
          'description_label' => 'Description',
          'description_rows' => 5,
          'key_size' => 60,
          'key_placeholder' => '',
          'description_enabled' => FALSE,
          'description_placeholder' => '',
        ],
        'third_party_settings' => [],
      ]);
      $field_group = $entity_form_display->getThirdPartySettings('field_group');
      $field_group['group_site_feature_flag_values'] = [
        'children' => [
          'field_site_feature_flags',
        ],
        'parent_name' => '',
        'label' => 'Site feature flag values',
        'weight' => 18,
        'format_type' => 'details',
        'region' => 'content',
        'format_settings' => [
          'classes' => '',
          'show_empty_fields' => FALSE,
          'id' => 'tide-feature-flag-fields',
          'open' => FALSE,
          'required_fields' => TRUE,
          'effect' => 'none',
        ],
      ];
      $entity_form_display->setThirdPartySetting('field_group', 'group_site_feature_flag_values', $field_group['group_site_feature_flag_values']);

      // Adds favicon.
      $entity_form_display->setComponent('field_site_favicon', [
        'type' => 'image_image',
        'weight' => 20,
        'region' => 'content',
        'settings' => [
          'progress_indicator' => 'throbber',
          'preview_image_style' => 'thumbnail',
        ],
        'third_party_settings' => [],
      ]);
      $field_group = $entity_form_display->getThirdPartySettings('field_group');
      $field_group['group_site_favicon_value'] = [
        'children' => [
          'field_site_favicon',
        ],
        'parent_name' => '',
        'label' => 'Site favicon value',
        'weight' => 19,
        'format_type' => 'details',
        'region' => 'content',
        'format_settings' => [
          'classes' => '',
          'show_empty_fields' => FALSE,
          'id' => 'tide-site-favicon-field',
          'open' => FALSE,
          'required_fields' => TRUE,
          'effect' => 'none',
        ],
      ];
      $entity_form_display->setThirdPartySetting('field_group', 'group_site_favicon_value', $field_group['group_site_favicon_value']);

      // Adds header corner graphics.
      $entity_form_display->setComponent('field_top_corner_graphic', [
        'type' => 'image_image',
        'weight' => 22,
        'region' => 'content',
        'settings' => [
          'progress_indicator' => 'throbber',
          'preview_image_style' => 'thumbnail',
        ],
        'third_party_settings' => [],
      ]);
      $entity_form_display->setComponent('field_bottom_corner_graphic', [
        'type' => 'image_image',
        'weight' => 23,
        'region' => 'content',
        'settings' => [
          'progress_indicator' => 'throbber',
          'preview_image_style' => 'thumbnail',
        ],
        'third_party_settings' => [],
      ]);
      $field_group = $entity_form_display->getThirdPartySettings('field_group');
      $field_group['group_site_header_corner_graphic'] = [
        'children' => [
          'field_top_corner_graphic',
          'field_bottom_corner_graphic',
        ],
        'parent_name' => '',
        'label' => 'Site header corner graphics',
        'weight' => 21,
        'format_type' => 'details',
        'region' => 'content',
        'format_settings' => [
          'classes' => '',
          'show_empty_fields' => FALSE,
          'id' => 'tide-site-header-corner-graphics',
          'open' => FALSE,
          'required_fields' => TRUE,
          'effect' => 'none',
        ],
      ];
      $entity_form_display->setThirdPartySetting('field_group', 'group_site_header_corner_graphic', $field_group['group_site_header_corner_graphic']);
    }
    $entity_form_display->save();

    // Adding the field to display view.
    $entity_view_display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('taxonomy_term.sites.default');
    if ($entity_view_display) {
      $entity_view_display->setComponent('field_site_theme_values', [
        'type' => 'key_value',
        'weight' => 16,
        'label' => 'above',
        'region' => 'content',
        'settings' => [
          'value_only' => FALSE,
        ],
        'third_party_settings' => [],
      ])->save();
      $entity_view_display->setComponent('field_site_feature_flags', [
        'type' => 'key_value',
        'weight' => 17,
        'label' => 'above',
        'region' => 'content',
        'settings' => [
          'value_only' => FALSE,
        ],
        'third_party_settings' => [],
      ])->save();
      $entity_view_display->setComponent('field_site_favicon', [
        'type' => 'image',
        'weight' => 18,
        'label' => 'above',
        'region' => 'content',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'svg_attributes' => [
            'width' => NULL,
            'height' => NULL,
          ],
          'svg_render_as_image' => TRUE,
          'image_loading' => [
            'attribute' => 'lazy',
          ],
        ],
        'third_party_settings' => [],
      ])->save();
      $entity_view_display->setComponent('field_top_corner_graphic', [
        'type' => 'image',
        'weight' => 19,
        'label' => 'above',
        'region' => 'content',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'svg_attributes' => [
            'width' => NULL,
            'height' => NULL,
          ],
          'svg_render_as_image' => TRUE,
          'image_loading' => [
            'attribute' => 'lazy',
          ],
        ],
        'third_party_settings' => [],
      ])->save();
      $entity_view_display->setComponent('field_bottom_corner_graphic', [
        'type' => 'image',
        'weight' => 20,
        'label' => 'above',
        'region' => 'content',
        'settings' => [
          'image_link' => '',
          'image_style' => '',
          'svg_attributes' => [
            'width' => NULL,
            'height' => NULL,
          ],
          'svg_render_as_image' => TRUE,
          'image_loading' => [
            'attribute' => 'lazy',
          ],
        ],
        'third_party_settings' => [],
      ])->save();
    }

    // Grant view preview links block to default roles from tide_core.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('site_admin');
    if ($role) {
      $role->grantPermission('tide site theming')->save();
    }
    // Add to JSON.
    $config_factory = \Drupal::configFactory();
    $config = $config_factory->getEditable('jsonapi_extras.jsonapi_resource_config.taxonomy_term--sites');
    $resourcefields_fields = [
      'field_site_theme_values',
      'field_site_feature_flags',
      'field_site_favicon',
      'field_top_corner_graphic',
      'field_bottom_corner_graphic',
    ];
    $content = $config->get('resourceFields');
    foreach ($resourcefields_fields as $field) {
      if (!isset($content[$field])) {
        $content[$field] = [
          'fieldName' => $field,
          'publicName' => $field,
          'enhancer' => [
            'id' => '',
          ],
          'disabled' => FALSE,
        ];
        $config->set('resourceFields', $content);
      }
    }
    $config->save();
  }

}
