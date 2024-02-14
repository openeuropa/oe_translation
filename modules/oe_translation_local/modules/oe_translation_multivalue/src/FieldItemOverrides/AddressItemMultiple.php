<?php

declare(strict_types=1);

namespace Drupal\oe_translation_multivalue\FieldItemOverrides;

use Drupal\address\Plugin\Field\FieldType\AddressItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Multivalue override class.
 */
class AddressItemMultiple extends AddressItem {

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
