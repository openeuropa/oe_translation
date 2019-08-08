<?php

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\oe_translation_poetry\Plugin\Field\FieldType\PoetryRequestIdItem;

/**
 * Plugin implementation of the 'Poetry Request ID Formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "poetry_request_id_formatter",
 *   label = @Translation("Poetry Request ID Formatter"),
 *   field_types = {
 *     "poetry_request_id"
 *   }
 * )
 */
class PoetryRequestIdFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $columns = PoetryRequestIdItem::getColumns();

    foreach ($items as $delta => $item) {
      $values = [];
      foreach ($columns as $column) {
        $values[] = $item->{$column};
      }

      $element[$delta] = [
        '#markup' => PoetryRequestIdItem::toReference($values)
      ];
    }

    return $element;
  }

}
