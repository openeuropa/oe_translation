<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * TMGMT translators that specify which entity types they work with.
 */
interface ApplicableTranslatorInterface {

  /**
   * Checks whether the translator can be used with this entity type.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   *   The entity type.
   *
   * @return bool
   *   Whether it applies.
   */
  public function applies(EntityTypeInterface $entityType): bool;

}
