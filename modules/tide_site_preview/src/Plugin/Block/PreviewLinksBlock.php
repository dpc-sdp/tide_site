<?php

namespace Drupal\tide_site_preview\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tide_site_preview\TideSitePreviewHelper;

/**
 * Class preview links block.
 *
 *  @Block(
 *   id = "tide_site_preview_links_block",
 *   admin_label = @Translation("Frontend Preview Links"),
 *   category = @Translation("Tide Site"),
 * )
 *
 * @package Drupal\tide_site_preview\Plugin\Block
 */
class PreviewLinksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Tide Site Helper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $siteHelper;

  /**
   * Tide Site Preview Helper service.
   *
   * @var \Drupal\tide_site_preview\TideSitePreviewHelper
   */
  protected $sitePreviewHelper;

  /**
   * Route Match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Entity Type Manage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $currentNode;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TideSiteHelper $site_helper, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager, TideSitePreviewHelper $site_preview_helper) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->siteHelper = $site_helper;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->sitePreviewHelper = $site_preview_helper;
    $this->getCurrentNode();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tide_site.helper'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager'),
      $container->get('tide_site_preview.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $form['open_new_window'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open preview links in a new window or browser tab'),
      '#default_value' => $config['open_new_window'] ?? TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['open_new_window'] = $values['open_new_window'];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('view tide_site preview links'));
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if (!$this->currentNode || !$this->isValidRoute()) {
      return [];
    }

    $preview_urls = [];
    // Load all sites of the current node.
    $sites = $this->siteHelper->getEntitySites($this->currentNode, TRUE);
    if (!empty($sites['ids'])) {
      // Generate the preview URLs on all sites.
      foreach ($sites['ids'] as $site_id) {
        $site = $this->siteHelper->getSiteById($site_id);
        if ($site) {
          $section = NULL;
          if (!empty($sites['sections'][$site_id])) {
            $section = $this->siteHelper->getSiteById($sites['sections'][$site_id]);
          }
          $preview_urls[$site_id] = $this->sitePreviewHelper->buildFrontendPreviewLink($this->currentNode, $site, $section, $this->getConfiguration());
        }
      }
    }

    // Prepend the preview URL of the primary site to the Preview Links.
    $primary_site = $this->siteHelper->getEntityPrimarySite($this->currentNode);
    if ($primary_site) {
      $primary_site_section = NULL;
      if (!empty($sites['sections'][$primary_site->id()])) {
        $primary_site_section = $this->siteHelper->getSiteById($sites['sections'][$primary_site->id()]);
      }
      $primary_preview_url = $this->sitePreviewHelper->buildFrontendPreviewLink($this->currentNode, $primary_site, $primary_site_section, $this->getConfiguration());
      unset($preview_urls[$primary_site->id()]);
      array_unshift($preview_urls, $primary_preview_url);
    }

    $config = $this->getConfiguration();
    $build = [
      '#theme' => 'tide_site_preview_links',
      '#node' => $this->currentNode,
      '#open_in_new_window' => !(empty($config['open_new_window'])),
      '#preview_links' => $preview_urls,
      '#attached' => [
        'library' => ['tide_site_preview/preview-links'],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    if ($this->currentNode) {
      $contexts = Cache::mergeTags($contexts, $this->currentNode->getCacheContexts());
    }
    return Cache::mergeContexts($contexts, [
      'url',
      'url.query_args',
      'user.roles',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();
    if ($this->currentNode) {
      $tags = Cache::mergeTags($tags, $this->currentNode->getCacheTags());
    }
    return $tags;
  }

  /**
   * Check if the current route is valid for this block.
   *
   * @return bool
   *   TRUE if the route is Node view or Node revision view.
   */
  protected function isValidRoute() : bool {
    // Only display on Node view and Node Revision view.
    $valid_routes = [
      'entity.node.revision',
      'entity.node.latest_version',
      'entity.node.canonical',
    ];
    $route_name = $this->routeMatch->getRouteName();
    return in_array($route_name, $valid_routes);
  }

  /**
   * Get the current node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node.
   */
  protected function getCurrentNode() : ?NodeInterface {
    if ($this->currentNode) {
      return $this->currentNode;
    }

    $route_name = $this->routeMatch->getRouteName();
    // Load the node from URL.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->routeMatch->getParameter('node');
    if ($route_name === 'entity.node.revision') {
      try {
        $vid = $this->routeMatch->getParameter('node_revision');
        $node = is_int($vid) ? $this->entityTypeManager->getStorage('node')->loadRevision($vid) : null;
      }
      catch (Exception $exception) {
        watchdog_exception('tide_site_preview', $exception);
        $node = NULL;
      }
    }

    if (!($node instanceof NodeInterface)) {
      $node = NULL;
    }

    $this->currentNode = $node;

    return $this->currentNode;
  }

}
