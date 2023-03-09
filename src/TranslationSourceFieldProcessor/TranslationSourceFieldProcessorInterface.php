<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\TranslationSourceFieldProcessor;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Interface for translation source field processors.
 *
 * @ingroup oe_translation
 */
interface TranslationSourceFieldProcessorInterface {

  /**
   * Extracts the translatable data structure from the given field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   *
   * @return array
   *   An array of elements where each element has the following keys:
   *   - #text
   *   - #translate
   */
  public function extractTranslatableData(FieldItemListInterface $field): array;

  /**
   * Sets the translatable data back onto the field.
   *
   * @param array $field_data
   *   The translated data for this field.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   */
  public function setTranslations(array $field_data, FieldItemListInterface $field): void;

}
