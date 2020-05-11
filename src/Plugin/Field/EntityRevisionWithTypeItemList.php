<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field;

use Drupal\Core\Field\FieldItemList;

/**
 * Field item list class for the EntityRevisionWithTypeItem field items.
 */
class EntityRevisionWithTypeItemList extends FieldItemList implements EntityRevisionWithTypeItemListInterface {

  /**
   * {@inheritdoc}
   */
  public function referencedEntities() {
    if ($this->isEmpty()) {
      return [];
    }

    $entity_type_manager = \Drupal::entityTypeManager();

    $entities = [];
    foreach ($this->list as $delta => $item) {
      $storage = $entity_type_manager->getStorage($item->entity_type);
      $entity_type = $entity_type_manager->getDefinition($item->entity_type);
      if (!$entity_type->isRevisionable()) {
        $entities[$delta] = $storage->load($item->entity_id);
        continue;
      }

      $entities[$delta] = $storage->loadRevision($item->entity_revision_id);
    }

    return $entities;
  }

}
