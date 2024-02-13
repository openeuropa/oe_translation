<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Plugin\Field;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines field item list classes for EntityRevisionWithTypeItem field items.
 */
interface EntityRevisionWithTypeItemListInterface extends FieldItemListInterface {

  /**
   * Gets the entities referenced by this field, preserving field item deltas.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects keyed by field item deltas.
   */
  public function referencedEntities();

}
