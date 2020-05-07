<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the EntityRevisionWithTypeItem formatter.
 *
 * @FieldFormatter(
 *   id = "oe_translation_entity_revision_type_item_formatter",
 *   label = @Translation("Entity revision with type item formatter"),
 *   field_types = {
 *     "oe_translation_entity_revision_type_item"
 *   }
 * )
 */
class EntityRevisionWithTypeItemFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (count($items) === 0) {
      return [];
    }

    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        'entity_id' => [
          '#plain_text' => $item->entity_id,
        ],
        'entity_revision' => [
          '#plain_text' => $item->entity_revision,
        ],
        'entity_type' => [
          '#plain_text' => $item->entity_type,
        ],
      ];
    }

    return $elements;
  }

}
