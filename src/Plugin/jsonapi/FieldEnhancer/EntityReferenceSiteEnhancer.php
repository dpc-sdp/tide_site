<?php

namespace Drupal\tide_site\Plugin\jsonapi\FieldEnhancer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\jsonapi_extras\Plugin\ResourceFieldEnhancerBase;
use Drupal\node\NodeInterface;
use Drupal\tide_api\TideApiHelper;
use Drupal\tide_site\TideSiteHelper;
use Shaper\Util\Context;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Enhancer with Site for entity reference field.
 *
 * @ResourceFieldEnhancer(
 *   id = "entity_reference_site_enhancer",
 *   label = @Translation("Site-enhanced Entity Reference"),
 *   description = @Translation("Enhancer with Site for entity reference field.")
 * )
 */
class EntityReferenceSiteEnhancer extends ResourceFieldEnhancerBase implements ContainerFactoryPluginInterface {

  /**
   * The Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The API Helper.
   *
   * @var \Drupal\tide_api\TideApiHelper
   */
  protected $helper;

  /**
   * Site helper.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $siteHelper;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, TideApiHelper $helper, TideSiteHelper $site_helper, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->helper = $helper;
    $this->siteHelper = $site_helper;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('tide_api.helper'),
      $container->get('tide_site.helper'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doUndoTransform($data, Context $context) {
    $data['meta']['type'] = $data['type'];
    $data['meta']['id'] = $data['id'];

    [$entity_type, $bundle] = explode('--', $data['type']);
    $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties([
      'type' => $bundle,
      'uuid' => $data['id'],
    ]);
    if (!empty($entities)) {
      /** @var \Drupal\Core\Entity\FieldableEntityInterface $entity */
      $entity = reset($entities);
      $data['meta']['entity_type'] = $entity_type;
      $data['meta']['bundle'] = $bundle;
      $data['meta']['title'] = $entity->label();

      try {
        $data['meta']['url'] = $entity->toUrl('canonical')->toString();

        $current_request = $this->requestStack->getCurrentRequest();
        $current_site_id = $current_request->get('site');
        if ($current_site_id
          && ($entity instanceof NodeInterface)
          && !$this->siteHelper->isEntityBelongToSite($entity, $current_site_id)
        ) {
          $data['meta']['url'] = $this->siteHelper->getNodeUrlFromPrimarySite($entity);
        }
      }
      catch (\Exception $exception) {
        watchdog_exception('tide_site', $exception);
        $data['meta']['url'] = '/' . $entity_type . '/' . $entity->id();
      }

      $entity_context = clone $entity;
      $this->moduleHandler->alter('tide_entity_reference_site_enhancer_undo_transform', $data, $context, $entity_context);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($value, Context $context) {
    unset($value['meta']['title'], $value['meta']['url']);
    if (empty($value['meta'])) {
      unset($value['meta']);
    }
    $this->moduleHandler->alter('tide_entity_reference_site_enhancer_transform', $value, $context);
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputJsonSchema() {
    return [
      'type' => 'object',
    ];
  }

}
