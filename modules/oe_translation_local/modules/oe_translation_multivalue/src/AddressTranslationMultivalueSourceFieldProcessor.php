<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Element;
use Drupal\oe_translation\TranslationSourceFieldProcessor\AddressFieldProcessor;
use Drupal\oe_translation\TranslationSourceFieldProcessor\DefaultFieldProcessor;

class AddressTranslationMultivalueSourceFieldProcessor extends AddressFieldProcessor {

  use MultivalueFieldProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    $this->setMultivalueTranslations($field_data, $field);
  }
}
