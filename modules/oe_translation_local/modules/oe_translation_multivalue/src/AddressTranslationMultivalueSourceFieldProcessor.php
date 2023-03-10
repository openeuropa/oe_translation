<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\oe_translation\TranslationSourceFieldProcessor\AddressFieldProcessor;

/**
 * Translation source manager field processor for the Address field.
 */
class AddressTranslationMultivalueSourceFieldProcessor extends AddressFieldProcessor {

  use MultivalueFieldProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    $this->setMultivalueTranslations($field_data, $field);
  }

}
