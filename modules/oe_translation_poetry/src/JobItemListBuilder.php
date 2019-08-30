<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tmgmt\Entity\ListBuilder\JobItemListBuilder as OriginalJobItemListBuilder;

/**
 * Override of the job item list builder to check for operations links access.
 */
class JobItemListBuilder extends OriginalJobItemListBuilder {

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    foreach ($operations as $name => $operation) {
      if (!$operation['url']->access()) {
        unset($operations[$name]);
      }
    }
    return $operations;
  }

}
