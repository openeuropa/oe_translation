<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue\FieldItemOverrides;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

class LinkItemMultiple extends LinkItem {

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
