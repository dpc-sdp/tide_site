<?php

/**
 * @file
 * Drush commands.
 */

/**
 * Implements hook_drush_command().
 */
function tide_site_drush_command() {
  $items['tide_site-migrate'] = [
    'description' => 'Move content from one Site to another Site.',
    'callback' => 'drush_tide_site_migrate',
    'drupal dependencies' => ['tide_site'],
    'arguments' => [
      'source_id' => 'Source Site ID',
      'destination_id' => 'Destination Site ID',
    ],
    'aliases' => ['tide_site:migrate', 'tsm'],
  ];

  return $items;
}

/**
 * Drush command callback.
 *
 * @param int $source_id
 *   Source Site ID.
 * @param int $destination_id
 *   Destination Site ID.
 */
function drush_tide_site_migrate($source_id, $destination_id) {
  try {
    $batch = \Drupal::service('tide_site.migrator')->getBatch($source_id, $destination_id);
    if (!empty($batch)) {
      batch_set($batch);
      $batch =& batch_get();

      // Because we are doing this on the back-end.
      $batch['progressive'] = FALSE;

      // Start processing the batch operations.
      drush_backend_batch_process();
    }
  }
  catch (\Exception $e) {
    drush_log($e->getMessage(), 'error');
  }
}
