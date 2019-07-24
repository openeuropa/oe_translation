<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\tmgmt\JobItemInterface;

/**
 * Interface for source translation info services.
 *
 * Services of this kind determine where the translation of a given entity is
 * saved. Used mostly for content entities which use revisions and we need to
 * determine the revision to save the translation onto.
 */
interface EntitySourceTranslationInfoInterface {

  /**
   * Returns the entity from the job item.
   *
   * @param \Drupal\tmgmt\JobItemInterface $jobItem
   *   The job item.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntityFromJobItem(JobItemInterface $jobItem): ?EntityInterface;

}
