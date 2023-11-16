<?php

namespace Drupal\tide_site\Plugin\Linkit\Substitution;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\linkit\Plugin\Linkit\Substitution\Canonical as LinkitCanonical;
use Drupal\node\NodeInterface;
use Drupal\tide_site\AliasStorageHelper;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the canonical links.
 *
 * @package Drupal\tide_site\Plugin\Linkit\Substitution
 */
class Canonical extends LinkitCanonical implements ContainerFactoryPluginInterface {

  /**
   * The Alias Storage helper service.
   *
   * @var \Drupal\tide_site\AliasStorageHelper
   */
  protected $aliasHelper;

  /**
   * Tide Site Helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $helper;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasStorageHelper $alias_helper, TideSiteHelper $helper, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasHelper = $alias_helper;
    $this->helper = $helper;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tide_site.alias_storage_helper'),
      $container->get('tide_site.helper'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity) {
    /** @var \Drupal\Core\GeneratedUrl $url */
    $url = parent::getUrl($entity)->toString(TRUE);

    // Remove the site prefix from path alias when responding from
    // JSONAPI entity resource with site parameter.
    $is_jsonapi = $this->request->attributes->get('_is_jsonapi', FALSE);
    if ($is_jsonapi) {
      $site_id = $this->request->get('site');
      if ($site_id && $entity instanceof NodeInterface) {
        if ($this->helper->isEntityBelongToSite($entity, $site_id)) {
          $alias = $url->getGeneratedUrl();
          $alias = $this->aliasHelper->getPathAliasWithoutSitePrefix(['alias' => $alias]);
          // The path alias is a relative URL.
          $url = Url::fromUri('internal:' . $alias)->toString(TRUE);
        }
        else {
          $site_url = $this->helper->getEntityPrimarySiteBaseUrl($entity);
          $alias = $this->helper->getNodeUrlFromPrimarySite($entity);
          $alias = $this->aliasHelper->getPathAliasWithoutSitePrefix(['alias' => $alias], $site_url);
          // The path alias is an absolute URL.
          $url = Url::fromUri($alias)->toString(TRUE);
        }
      }
    }

    return $url;
  }

}
