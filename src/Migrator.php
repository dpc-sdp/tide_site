<?php

namespace Drupal\tide_site;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Class Migrator.
 */
class Migrator {
  use StringTranslationTrait;
  use MessengerTrait;

  /**
   * Batch size.
   */
  const BATCH_SIZE = 20;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Helper service.
   *
   * @var \Drupal\tide_site\TideSiteHelper
   */
  protected $helper;

  /**
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\tide_site\TideSiteHelper $helper
   *   The helper service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, TideSiteHelper $helper, TranslationInterface $string_translation, TimeInterface $time) {
    $this->entityTypeManager = $entity_type_manager;
    $this->helper = $helper;
    $this->stringTranslation = $string_translation;
    $this->time = $time;
  }

  /**
   * Generate batch to migrate content from Source Site to Destination Site.
   *
   * @param int $source_id
   *   Source Site ID.
   * @param int $destination_id
   *   Destination Site ID.
   *
   * @return array
   *   Batch definitions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBatch($source_id, $destination_id) {
    $source = $this->helper->getSiteById($source_id);
    $destination = $this->helper->getSiteById($destination_id);
    if (!$source || !$destination) {
      return [];
    }

    $batch = [];
    $entity_ids = [];

    // Load all entities of the Source site.
    foreach ($this->helper->getSupportedEntityTypes() as $entity_type) {
      $field_site_field_name = TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_SITE, $entity_type);

      $query = $this->entityTypeManager->getStorage($entity_type)->getQuery();
      $query->latestRevision()->condition($field_site_field_name, $source_id);
      $ids = $query->execute();
      if (!empty($ids)) {
        $entity_ids[$entity_type] = array_chunk($ids, self::BATCH_SIZE);
      }
    }

    // Prepare the batch.
    if (!empty($entity_ids)) {
      $batch = [
        'title' => $this->t('Moving content from Site @source (@source_id) to @destination (@destination_id)...', [
          '@source' => $source->getName(),
          '@source_id' => $source->id(),
          '@destination' => $destination->getName(),
          '@destination_id' => $destination->id(),
        ]),
        'operations' => [],
        'finished' => 'Drupal\tide_site\Migrator::batchFinishedCallback',
        'progress_message' => $this->t('Processed @current out of @total.'),
      ];

      foreach ($entity_ids as $entity_type => $chunks) {
        foreach ($chunks as $ids) {
          $batch['operations'][] = [
            'Drupal\tide_site\Migrator::batchProcessCallback',
            [
              $entity_type,
              $source_id,
              $destination_id,
              $ids,
            ],
          ];
        }
      }
    }

    return $batch;
  }

  /**
   * Migrate entities from one site to another site..
   *
   * @param string $entity_type
   *   Entity type.
   * @param int $source_id
   *   Source Site ID.
   * @param int $destination_id
   *   Destination Site ID.
   * @param array $ids
   *   List of entity ID to migrate.
   * @param mixed $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function migrate($entity_type, $source_id, $destination_id, array $ids, &$context) {
    $field_site_field_name = TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_SITE, $entity_type);
    $field_primary_site_field_name = TideSiteFields::normaliseFieldName(TideSiteFields::FIELD_PRIMARY_SITE, $entity_type);

    $source = $this->helper->getSiteById($source_id);
    if (!$source) {
      $context['errors'][] = $this->t('Invalid source Site ID @id.', ['@id' => $source_id]);
      return;
    }
    $destination = $this->helper->getSiteById($destination_id);
    if (!$destination) {
      $context['errors'][] = $this->t('Invalid destination Site ID @id.', ['@id' => $destination_id]);
      return;
    }

    $entities = $this->entityTypeManager->getStorage($entity_type)->loadMultiple($ids);
    /** @var \Drupal\Core\Entity\EditorialContentEntityBase $entity */
    foreach ($entities as $entity) {
      $data_changed = FALSE;

      try {
        // Migrate the Sites field.
        if ($entity->hasField($field_site_field_name)) {
          $field_site = $entity->get($field_site_field_name);
          if (!$field_site->isEmpty()) {
            $entity_sites = $this->helper->getEntitySites($entity, TRUE);

            $values = $field_site->getValue();
            foreach ($values as $delta => $value) {
              // Remove the source site.
              if ($value['target_id'] == $source_id) {
                unset($values[$delta]);
              }
              // Remove the section of the source site.
              if (!empty($entity_sites['sections'][$source_id]) && $value['target_id'] == $entity_sites['sections'][$source_id]) {
                unset($values[$delta]);
              }
              // Remove the destination site, it will be re-add later.
              if ($value['target_id'] == $destination_id) {
                unset($values[$delta]);
              }
            }
            $values = array_values($values);
            $values[] = ['target_id' => $destination_id];
            $entity->set($field_site_field_name, $values);
            $data_changed = TRUE;
          }
        }

        // Migrate the Primary Site field.
        if ($entity->hasField($field_primary_site_field_name)) {
          $primary_site = $this->helper->getEntityPrimarySite($entity);
          if ($primary_site->id() == $source_id) {
            $entity->set($field_primary_site_field_name, ['target_id' => $destination_id]);
            $data_changed = TRUE;
          }
        }

        if ($data_changed) {
          // Create a new revision.
          $entity->setNewRevision(TRUE);
          $entity->revision_log = $this->t('Moved from site @source (@source_id) to @destination (@destination_id)', [
            '@source' => $source->getName(),
            '@source_id' => $source->id(),
            '@destination' => $destination->getName(),
            '@destination_id' => $destination->id(),
          ]);
          $entity->setRevisionCreationTime($this->time->getRequestTime());
          $entity->setRevisionUserId(1);

          // Maintain the moderation state.
          if ($entity->hasField('moderation_state')) {
            $current_state = $entity->get('moderation_state')->getValue();
            $entity->set('moderation_state', $current_state);
          }
          // Otherwise maintain the publishing status.
          else {
            $entity->setPublished($entity->isPublished());
          }

          $entity->save();

          $context['results'][$entity_type][] = $entity->id();
        }
      }
      catch (\Exception $e) {
        watchdog_exception('tide_site', $e);
        $context['errors'][] = $e->getMessage();
      }
    }
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether batch succeeds.
   * @param array $results
   *   The results.
   * @param array $operations
   *   The operations.
   */
  public static function batchFinishedCallback($success, array $results, array $operations) {
    if (!$success) {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      $error = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      \Drupal::messenger()->addError($error);
    }

    foreach ($results as $entity_type => $ids) {
      $message = \Drupal::translation()->formatPlural(
        count($ids),
        '@entity_type: 1 entity moved.', '@entity_type: @count entities moved.',
        ['@entity_type' => $entity_type]
      );
      \Drupal::messenger()->addMessage($message);
    }

    if (!empty($results['errors'])) {
      foreach ($results['errors'] as $error) {
        \Drupal::messenger()->addError($error);
      }
    }
  }

  /**
   * Batch process callback.
   *
   * @param string $entity_type
   *   Entity type.
   * @param int $source_id
   *   Source Site ID.
   * @param int $destination_id
   *   Destination Site ID.
   * @param array $ids
   *   List of ID to process.
   * @param mixed $context
   *   Batch context.
   */
  public static function batchProcessCallback($entity_type, $source_id, $destination_id, array $ids, &$context) {
    $service = \Drupal::service('tide_site.migrator');
    $service->migrate($entity_type, $source_id, $destination_id, $ids, $context);
  }

}
