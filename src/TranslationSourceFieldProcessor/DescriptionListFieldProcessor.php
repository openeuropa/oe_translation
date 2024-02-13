<?php

declare(strict_types=1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Translation source field processor for the description list field.
 */
class DescriptionListFieldProcessor extends DefaultFieldProcessor {

  /**
   * {@inheritdoc}
   */
  public function extractTranslatableData(FieldItemListInterface $field): array {
    $data = parent::extractTranslatableData($field);

    // Remove the #format from the columns which actually should not have a
    // text format.
    foreach ($data as $delta => &$value) {
      if (!is_numeric($delta)) {
        continue;
      }
      if (isset($value['term']['#format'])) {
        unset($value['term']['#format']);
      }
    }

    return $data;
  }

}
