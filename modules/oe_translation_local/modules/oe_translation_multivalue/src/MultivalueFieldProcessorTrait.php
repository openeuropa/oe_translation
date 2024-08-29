<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Trait for handling the logic in the translation source field processor.
 */
trait MultivalueFieldProcessorTrait {

  /**
   * {@inheritdoc}
   */
  public function setMultivalueTranslations($field_data, FieldItemListInterface $field): void {
    parent::setTranslations($field_data, $field);

    foreach (Element::children($field_data) as $delta) {
      $field_item = $field_data[$delta];
      foreach (Element::children($field_item) as $property) {
        $property_data = $field_item[$property];
        if ($property === 'translation_id' && $field->offsetExists($delta)) {
          // Keep the translation ID in sync whenever we sync the translation,
          // if that offset exists (in rare cases it can be missing, for example
          // when one of the deltas doesn't have anythin translatable about
          // it while others do).
          $field->offsetGet($delta)->set($property, $property_data['#text']);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function shouldTranslateProperty(TypedDataInterface $property): bool {
    if ($property->getName() === 'translation_id') {
      return FALSE;
    }
    return parent::shouldTranslateProperty($property);
  }

}
