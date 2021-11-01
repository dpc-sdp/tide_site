<?php

/**
 * @file
 * Post update functions for tide_site.
 */

/**
 * Set footer_main_menu to have same value as site_main_menu .
 */
function tide_site_post_update_sites_vocab_footer_main_menu() {
  $query = \Drupal::entityQuery('taxonomy_term');
  $query->condition('vid', "sites");
  $tids = $query->execute();
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($tids);
  if (!empty($terms)) {
    foreach ($terms as $term) {
      if (isset($term->get('field_site_main_menu')->target_id)) {
        $term->field_site_footer_main_menu->setValue($term->get('field_site_main_menu')->target_id);
        $term->Save();
      }
    }
  }
}
