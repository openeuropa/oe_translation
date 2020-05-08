<?php

namespace Drupal\oe_translation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Defines the 'Translation Synchronisation Formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "oe_translation_translation_sync_formatter",
 *   label = @Translation("Translation Synchronisation Formatter"),
 *   field_types = {
 *     "oe_translation_translation_sync"
 *   }
 * )
 */
class TranslationSynchronisationFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (count($items) === 0) {
      return [];
    }
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta]['type'] = [
        '#plain_text' => $item->type,
      ];
    }

    return $elements;
  }

}
