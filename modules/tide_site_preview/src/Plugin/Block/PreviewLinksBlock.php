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
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TideSiteHelper $site_helper, RouteMatchInterface $route_match, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->siteHelper = $site_helper;
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('entity_type.manager')
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
          $preview_urls[$site_id] = $this->buildFrontendPreviewLink($this->currentNode, $site, $section);
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
      $primary_preview_url = $this->buildFrontendPreviewLink($this->currentNode, $primary_site, $primary_site_section);
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
        $node = $this->entityTypeManager->getStorage('node')
          ->loadRevision($vid);
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

  /**
   * Build the frontend preview link array of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param \Drupal\taxonomy\TermInterface $site
   *   The site of the preview link.
   * @param \Drupal\taxonomy\TermInterface|null $section
   *   The section of the preview link.
   *
   * @return array
   *   The preview link array with following keys:
   *   * #site: The site object.
   *   * #section: The section object.
   *   * name: The site/section name.
   *   * url: The absolute URL of the preview link.
   */
  protected function buildFrontendPreviewLink(NodeInterface $node, TermInterface $site, TermInterface $section = NULL) : array {
    $config = $this->getConfiguration();
    $url_options = [
      'attributes' => !(empty($config['open_new_window'])) ? ['target' => '_blank'] : [],
    ];
    if ($section) {
      $url_options['query']['section'] = $section->id();
    }

    $preview_link = [
      '#site' => $site,
      '#section' => $section,
      'name' => $site->getName(),
    ];
    if ($section && $section->id() !== $site->id()) {
      $preview_link['name'] = $site->getName() . ' - ' . $section->getName();
    }
    $site_base_url = $this->siteHelper->getSiteBaseUrl($site);
    if ($node->isPublished() && $node->isDefaultRevision()) {
      unset($url_options['query']['section']);
      $preview_link['url'] = $this->getNodeFrontendUrl($node, $site_base_url, $url_options);
    }
    else {
      $revision_id = $node->getLoadedRevisionId();
      $is_latest_revision = $node->isLatestRevision();
      $content_type = $node->bundle();
      $url = !empty($site_base_url) ? ($site_base_url . '/preview/' . $content_type . '/' . $node->uuid() . '/' . ($is_latest_revision ? 'latest' : $revision_id)) : '';
      $preview_link['url'] = (!empty($url) && !empty($url_options)) ? Url::fromUri($url, $url_options) : '';
    }

    return $preview_link;
  }

  /**
   * Get the frontend URL of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $site_base_url
   *   The base URL of the frontend.
   * @param array $url_options
   *   The extra options.
   *
   * @return \Drupal\Core\Url|string
   *   The Url.
   */
  protected function getNodeFrontendUrl(NodeInterface $node, $site_base_url = '', array $url_options = []) {
    try {
      $url = $node->toUrl('canonical', [
        'absolute' => TRUE,
        'base_url' => $site_base_url,
      ] + $url_options);

      $pattern = '/^\/site\-(\d+)\//';
      if ($site_base_url) {
        $pattern = '/' . preg_quote($site_base_url, '/') . '\/site\-(\d+)\//';
      }
      $clean_url = preg_replace($pattern, $site_base_url . '/', $url->toString());
      return $clean_url ? Url::fromUri($clean_url, $url_options) : $url;
    }
    catch (Exception $exception) {
      watchdog_exception('tide_site_preview', $exception);
    }
    return '';
  }

}
