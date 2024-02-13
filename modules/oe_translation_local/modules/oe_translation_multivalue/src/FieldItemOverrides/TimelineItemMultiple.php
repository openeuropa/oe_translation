<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue\FieldItemOverrides;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\oe_content_timeline_field\Plugin\Field\FieldType\TimelineFieldItem;

/**
 * Multivalue override class.
 */
class TimelineItemMultiple extends TimelineFieldItem {

  use TranslationMultivalueFieldTrait;

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return static::overridePropertyDefinitions($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return static::overrideSchema($field_definition);
  }

}
