<?php

/**
 * @file
 * Drush commands for Tide Site.
 */

use Drupal\taxonomy\Entity\Term;

/**
 * Implements hook_drush_command().
 */
function tide_site_drush_command() {
  $items = [];

  $items['tide-site-env-domain-update'] = [
    'description' => 'Update the domains on the site taxonomy based on an environment variable.',
    'examples' => [
      'drush tide-site-env-domain-update' => dt('Update the domains on the site taxonomy based on an environment variable.'),
    ],
    'arguments' => [],
    'aliases' => ['tide-si-domup'],
  ];

  return $items;
}

/**
 * Update the domains on the site taxonomy based on an environment variable.
 */
function drush_tide_site_env_domain_update() {

  try {
    $environment = getenv('LAGOON_GIT_BRANCH');
    if ($environment == 'production') {
      drush_print('This command cannot run in Lagoon production environments.');
    }
    else {
      $fe_domains = getenv('FE_DOMAINS');
      if (!empty($fe_domains)) {
        foreach (explode(',', $fe_domains) as $fe_domain) {
          $domain = explode('|', $fe_domain);
          $term = Term::load($domain[0]);
          $term->set('field_site_domains', str_replace('<br/>', "\r\n", $domain[1]));
          $term->save();
        }
        drush_print('Domains Updated.');
      }
      else {
        drush_print('No site specific domains were found in this environment.');
      }
    }
  }
  catch (ConsoleException $exception) {
    drush_set_error($exception->getMessage());
  }
}
