<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Interface for entity revision info services.
 *
 * Services of this kind determine where the translation of a given entity is
 * saved. Used mostly for content entities which use revisions and we need to
 * determine the revision to save the translation onto.
 */
interface EntityRevisionInfoInterface {

  /**
   * Returns the correct entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The original entity.
   * @param string $target_langcode
   *   The target langcode.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity revision.
   */
  public function getEntityRevision(ContentEntityInterface $entity, string $target_langcode): ContentEntityInterface;

}
