<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;

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
        if ($property === 'translation_id') {
          $field->offsetGet($delta)->set($property, $property_data['#text']);
        }
      }
    }
  }
}
