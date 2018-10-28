<?php

namespace Drupal\tide_site\Commands;

/**
 * @file
 * Drush commands.
 */

use Drupal\tide_site\Migrator;
use Drush\Commands\DrushCommands;

/**
 * Class TideSiteCommands.
 */
class TideSiteCommands extends DrushCommands {

  /**
   * The Migrator service.
   *
   * @var \Drupal\tide_site\Migrator
   */
  protected $migrator;

  /**
   * TideSiteCommands constructor.
   *
   * @param \Drupal\tide_site\Migrator $migrator
   *   The migrator service.
   */
  public function __construct(Migrator $migrator) {
    $this->migrator = $migrator;
  }

  /**
   * Move content from one Site to another Site.
   *
   * @param int $source_id
   *   Source Site ID.
   * @param int $destination_id
   *   Destination Site ID.
   *
   * @command tide_site:migrate
   * @aliases tide_site-migrate tsm
   * @usage tide_site:migrate <SOURCE_ID> <DESTINATION_ID>
   *   Move content from site <SOURCE_ID> to <DESTINATION_ID>.
   */
  public function migrate($source_id, $destination_id) {
    try {
      $batch = $this->migrator->getBatch($source_id, $destination_id);
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
      $this->output()->writeln($e->getMessage());
    }
  }

}
