<?php

namespace Drupal\tide_site;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\AliasManager as CoreAliasManager;
use Drupal\Core\Path\AliasStorageInterface;
use Drupal\Core\Path\AliasWhitelistInterface;

/**
 * Class AliasManager.
 *
 * @package Drupal\tide_site
 */
class AliasManager extends CoreAliasManager {

  /**
   * The Alias Storage Helper service.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $aliasHelper;

  /**
   * {@inheritdoc}
   */
  public function __construct(AliasStorageInterface $storage, AliasWhitelistInterface $whitelist, LanguageManagerInterface $language_manager, CacheBackendInterface $cache, AliasStorageHelper $alias_helper) {
    parent::__construct($storage, $whitelist, $language_manager, $cache);
    $this->aliasHelper = $alias_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function getAliasByPath($path, $langcode = NULL) {
    $alias = parent::getAliasByPath($path, $langcode);

    // Remove the site prefix from path alias when responding from
    // JSONAPI entity resource with site parameter.
    $request = \Drupal::request();
    $controller = $request->attributes->get('_controller');
    if ($controller == 'jsonapi.request_handler:handle') {
      $site_id = $request->get('site');
      if ($site_id) {
        $alias = $this->aliasHelper->getPathAliasWithoutSitePrefix(['alias' => $alias]);
      }
    }

    return $alias;
  }

}
