<?php

namespace Drupal\tide_site\Commands;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class TideSiteCommands extends DrushCommands {

  use StringTranslationTrait;

  /**
   * Update the domains on the site taxonomy based on an environment variable.
   *
   * @usage drush tide-site-env-domain-update
   *   Update the domains on the site taxonomy based on an environment variable.
   *
   * @command tide:site-env-domain-update
   * @aliases tide-si-domup,tide-site-env-domain-update
   *
   * @throws \Exception
   */
  public function siteEnvDomainUpdate() {
    try {
      $environment = getenv('LAGOON_GIT_BRANCH');
      if ($environment == 'production') {
        $this->output()->writeln($this->t('This command cannot run in Lagoon production environments.'));
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
          $this->output()->writeln($this->t('Domains Updated.'));
        }
        else {
          $this->output()->writeln($this->t('No site specific domains were found in this environment.'));
        }
      }
    }
    catch (ConsoleException $exception) {
      throw new \Exception($exception->getMessage());
    }
  }

}
