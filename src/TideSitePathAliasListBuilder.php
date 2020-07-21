<?php

namespace Drupal\tide_site;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\path\PathAliasListBuilder;

/**
 * TideSitePathAliasListBuilder.
 *
 * We don't want the alias link to be truncated in the listing page.
 */
class TideSitePathAliasListBuilder extends PathAliasListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $parent = parent::buildRow($entity);
    $alias = $entity->getAlias();
    $path = $entity->getPath();
    $url = Url::fromUserInput($path);
    $parent['data']['alias']['data'] = [
      '#type' => 'link',
      '#title' => $alias,
      '#url' => $url->setOption('attributes', ['title' => $alias]),
    ];
    return $parent;
  }

}
