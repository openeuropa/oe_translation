<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\oe_translation_epoetry\Plugin\Field\FieldType\RequestIdItem;

/**
 * Plugin implementation of the 'ePoetry Request ID Formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "epoetry_request_id_formatter",
 *   label = @Translation("ePoetry Request ID Formatter"),
 *   field_types = {
 *     "epoetry_request_id"
 *   }
 * )
 */
class RequestIdFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $columns = RequestIdItem::getColumns();

    foreach ($items as $delta => $item) {
      $values = [];
      foreach ($columns as $column) {
        $values[] = $item->{$column};
      }

      $element[$delta] = [
        '#markup' => RequestIdItem::toReference($values),
      ];
    }

    return $element;
  }

}
