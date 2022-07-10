<?php

/**
 * @file
 * Tokens.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info_alter().
 */
function tide_site_token_info_alter(&$data) {
  $data['tokens']['url']['path-no-site-prefix'] = [
    'name' => t('Path without Site Prefix'),
    'description' => t('The base path component without site prefix.'),
  ];

  $data['tokens']['node']['publication-parent-prefix'] = [
    'name' => t('Publication parent with prefix'),
    'description' => t('The publication parent to use with linkit'),
  ];
}

/**
 * Implements hook_tokens().
 */
function tide_site_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  $language_manager = \Drupal::languageManager();
  $url_options = ['absolute' => TRUE];
  if (isset($options['langcode'])) {
    $url_options['language'] = $language_manager->getLanguage($options['langcode']);
    $langcode = $options['langcode'];
  }
  else {
    $langcode = $language_manager->getCurrentLanguage()->getId();
  }

  if ($type == 'url' && !empty($data['url'])) {
    /** @var \Drupal\Core\Url $url */
    $url = $data['url'];
    // To retrieve the correct path, modify a copy of the Url object.
    $path_url = clone $url;
    $path = '/';
    // Ensure the URL is routed to avoid throwing an exception.
    if ($url->isRouted()) {
      $path .= $path_url->setAbsolute(FALSE)->setOption('fragment', NULL)->getInternalPath();
    }
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'path-no-site-prefix':
          $value = !($url->getOption('alias')) ? \Drupal::service('path_alias.manager')->getAliasByPath($path, $langcode) : $path;
          $value = preg_replace("/^\/site\-(\d+)\//", '/', $value, 1);
          $replacements[$original] = $value;
          break;
      }
    }
  }

  if ($type == 'node' && !empty($data['node'])) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'publication-parent-prefix':
          $node = $data['node'];
          if ($node->getType() == 'publication_page') {
            if ($node->hasField('field_publication') && !$node->get('field_publication')->isEmpty()) {
              $text = $node->field_publication->entity->getTitle();
              $value = "| Parent: " . $text;
            }
            $replacements[$original] = $value;
            break;
          }
      }
    }
  }

  return $replacements;
}
