<?php

namespace Drupal\tide_site\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType;
use Drupal\tide_site\TideSiteFields;
use Drupal\tide_site\TideSiteHelper;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class TideApiRequestEventSubscriber.
 *
 * @package Drupal\tide_api\EventSubscriber
 */
class TideSiteRequestEventSubscriber implements EventSubscriberInterface {
  use ContainerAwareTrait;
  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The Tide Site Helper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $helper;

  /**
   * The state of JSON API module.
   *
   * @var bool
   */
  protected $jsonApiEnabled = FALSE;

  /**
   * JsonApiExtrasRouteAlterSubscriber constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\tide_site\TideSiteHelper $helper
   *   The Tide Site Helper service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, TideSiteHelper $helper) {
    $this->moduleHandler = $module_handler;
    $this->helper = $helper;
    $this->jsonApiEnabled = $this->moduleHandler->moduleExists('jsonapi');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Our subscriber must have very low priority,
    // as it relies on route resolver to parse all params.
    $events[KernelEvents::REQUEST][] = ['onRequestAddSiteFilter', -10000];
    // Run after JSON API ResourceResponseSubscriber (priority 128) and
    // before DynamicPageCacheSubscriber (priority 100).
    $events[KernelEvents::RESPONSE][] = ['onResponseAddSiteFilterCacheContext', 127];

    return $events;
  }

  /**
   * Add Site filter to the request of JSON API controller.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event.
   *
   * @see \Symfony\Component\HttpKernel\HttpKernel::handleRaw()
   * @see \Drupal\jsonapi\Controller\RequestHandler::handle()
   */
  public function onRequestAddSiteFilter(GetResponseEvent $event) {
    if (!$this->jsonApiEnabled) {
      return;
    }

    $request = $event->getRequest();

    $site_id = $request->query->get('site');

    $controller = $request->attributes->get('_controller');

    // Prefix path with Site if the controller is TideApiController.
    if ($controller == '\\Drupal\\tide_api\\Controller\\TideApiController::getRoute' && $site_id) {
      $path = $request->query->get('path');
      // Only prefix non-homepage and unrouted path.
      if ($path !== '/') {
        try {
          $url = Url::fromUri('internal:' . $path);
          if (!$url->isRouted()) {
            $request->query->set('path', $this->helper->getSitePathPrefix($site_id) . $path);
          }
        }
        catch (\Exception $exception) {
          // No URI, does nothing.
        }
      }
    }

    // Only works with JSON API routes.
    if ($controller != 'jsonapi.request_handler:handle') {
      return;
    }

    /** @var \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type */
    $resource_type = $request->attributes->get('resource_type');
    $entity_type = $resource_type->getEntityTypeId();
    $bundle = $resource_type->getBundle();

    // Only works with restricted entity types.
    if (!$this->helper->isRestrictedEntityType($entity_type)) {
      return;
    }

    $field_site_name = $this->buildFieldName($entity_type);

    $entity = $request->get($entity_type);
    // The current route has an entity in its params,
    // it's to retrieve an individual entity.
    if ($entity) {
      // Only process if the entity has Sites.
      $sites = $this->helper->getEntitySites($entity);
      if ($sites) {
        // No Site ID provided.
        if (!$site_id) {
          // This entity has Sites but our required parameter is missing,
          // so we stop processing and return a Bad Request 400 code.
          $this->setEventErrorResponse($event, $this->t('URL query parameter "site" is required.'), Response::HTTP_BAD_REQUEST);
          return;
        }
        // Check if the entity belongs to the requested site.
        else {
          $valid = $this->helper->isEntityBelongToSite($entity, $site_id);
          if ($valid) {
            $individual_route = 'jsonapi.' . $bundle . '.individual';
            $route = $request->attributes->get('_route');
            // The current route is to retrieve relationship of the entity.
            if ($route != $individual_route) {
              // We add Site filter.
              $site_filter = [
                'condition' => [
                  'path' => $field_site_name . '.tid',
                  'operator' => '=',
                  'value' => $site_id,
                ],
              ];
              $this->setSiteFilterToJsonApi($request, $site_filter, $resource_type);
            }
          }
          // The entity does not belong to the requested Site.
          else {
            $this->setEventErrorResponse($event, $this->t('Path not found.'), Response::HTTP_NOT_FOUND);
            return;
          }
        }
      }
    }
    // It's to retrieve a collection.
    else {
      // Site ID is provided, we filter the collection using the site ID.
      if ($site_id) {
        if ($this->helper->isValidSite($site_id)) {
          $site_filter = [
            'condition' => [
              'path' => $field_site_name . '.tid',
              'operator' => '=',
              'value' => $site_id,
            ],
          ];
        }
        else {
          $this->setEventErrorResponse($event, $this->t('Invalid Site ID.'), Response::HTTP_BAD_REQUEST);
          return;
        }
      }
      // No Site ID, JSON API should only return entities without a Site.
      else {
        $site_filter = [
          'condition' => ['path' => $field_site_name . '.tid', 'operator' => 'IS NULL'],
        ];
      }

      $this->setSiteFilterToJsonApi($request, $site_filter, $resource_type);
    }
  }

  /**
   * Add Site to cache context and tags of JSON API response.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event object.
   */
  public function onResponseAddSiteFilterCacheContext(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    $site_id = $event->getRequest()->query->get('site');
    if ($site_id) {
      $context = $response->getCacheableMetadata()->getCacheContexts();
      $context = Cache::mergeContexts($context, ['url.query_args:site']);
      $response->getCacheableMetadata()->setCacheContexts($context);

      $cache_tags = $response->getCacheableMetadata()->getCacheTags();
      $cache_tags = Cache::mergeTags($cache_tags, ['taxonomy_term:' . $site_id]);
      $response->getCacheableMetadata()->setCacheTags($cache_tags);
    }
  }

  /**
   * Set Site filter to JSON API.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param array $site_filter
   *   The Site filter array.
   * @param \Drupal\jsonapi_extras\ResourceType\ConfigurableResourceType $resource_type
   *   The Resource type.
   */
  protected function setSiteFilterToJsonApi(Request $request, array $site_filter, ConfigurableResourceType $resource_type) {
    $filter = $request->query->get('filter', []);
    $filter['site'] = $site_filter;

    // Convert the filter query to JSON API Filter object.
    $context = [
      'entity_type_id' => $resource_type->getEntityTypeId(),
      'bundle' => $resource_type->getBundle(),
    ];
    /** @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface $filter_normalizer */
    $filter_normalizer = $this->container->get('serializer.normalizer.filter.jsonapi');
    $jsonapi_filter = $filter_normalizer->denormalize($filter, '\Drupal\jsonapi\Query\Filter', NULL, $context);

    // Update the filter of JSON API.
    $route_params = $request->attributes->get('_route_params');
    $route_params['_json_api_params']['filter'] = $jsonapi_filter;
    $request->attributes->set('_route_params', $route_params);
  }

  /**
   * Create a JSON Response for error message.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The event.
   * @param string $error_message
   *   The error message.
   * @param int $code
   *   The error code, default to 400.
   */
  protected function setEventErrorResponse(GetResponseEvent $event, $error_message, $code = Response::HTTP_BAD_REQUEST) {
    $json_response = [
      'links' => [
        'self' => Url::fromRoute('<current>')->setAbsolute()->toString(),
      ],
      'errors' => [$error_message],
    ];
    $response = new JsonResponse($json_response, $code);
    $event->setResponse($response);
    $event->stopPropagation();
  }

  /**
   * Helper to build field name for provided entity type.
   *
   * @param string $entity_type
   *   Entity type.
   *
   * @return string
   *   Site field name.
   */
  protected function buildFieldName($entity_type) {
    return TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_SITE, $entity_type);
  }

}
