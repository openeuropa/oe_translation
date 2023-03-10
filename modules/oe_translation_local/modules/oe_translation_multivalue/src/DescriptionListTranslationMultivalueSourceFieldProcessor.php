<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\oe_translation\TranslationSourceFieldProcessor\DescriptionListFieldProcessor;

/**
 * Translation source manager field processor for the Description List field.
 */
class DescriptionListTranslationMultivalueSourceFieldProcessor extends DescriptionListFieldProcessor {

  use MultivalueFieldProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    $this->setMultivalueTranslations($field_data, $field);
  }

}
