<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_translation\Plugin\Field\FieldType\TranslationSynchronisationItem;
use Drupal\oe_translation\Plugin\Field\FieldWidget\TranslationSynchronisationWidget;

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
        '#plain_text' => static::getSyncTypeLabel($item),
      ];
    }

    return $elements;
  }

  /**
   * Returns the label of the synchronization type of a field.
   *
   * @param \Drupal\oe_translation\Plugin\Field\FieldType\TranslationSynchronisationItem $item
   *   The field item.
   *
   * @return string
   *   The label.
   */
  public static function getSyncTypeLabel(TranslationSynchronisationItem $item): TranslatableMarkup {
    $options = TranslationSynchronisationWidget::getSyncTypeOptions();
    return $options[$item->type];
  }

}
