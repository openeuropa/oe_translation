<?php

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a view controller for a translation request entity type.
 */
class TranslationRequestViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getBuildDefaults(EntityInterface $entity, $view_mode) {
    $build = parent::getBuildDefaults($entity, $view_mode);
    // The translation request has no entity template itself.
    unset($build['#theme']);
    return $build;
  }

}
