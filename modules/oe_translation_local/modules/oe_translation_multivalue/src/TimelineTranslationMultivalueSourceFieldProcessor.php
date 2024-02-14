<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\oe_content_timeline_field\TimelineFieldProcessor;

/**
 * Translation source manager field processor for the Address field.
 */
class TimelineTranslationMultivalueSourceFieldProcessor extends TimelineFieldProcessor {

  use MultivalueFieldProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function setTranslations($field_data, FieldItemListInterface $field): void {
    $this->setMultivalueTranslations($field_data, $field);
  }

}
