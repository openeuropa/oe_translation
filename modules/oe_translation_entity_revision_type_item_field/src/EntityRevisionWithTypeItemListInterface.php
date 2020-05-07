<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field;

/**
 * Defines field item list classes for EntityRevisionWithTypeItem field items.
 */
interface EntityRevisionWithTypeItemListInterface {

  /**
   * Gets the entities referenced by this field, preserving field item deltas.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of entity objects keyed by field item deltas.
   */
  public function referencedEntities();

}
