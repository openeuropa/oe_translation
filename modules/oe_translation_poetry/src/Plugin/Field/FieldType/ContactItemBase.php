<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Base class for contact item field types.
 */
abstract class ContactItemBase extends FieldItemBase implements ContactItemInterface {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $empty = TRUE;
    $types = static::contactTypes();
    foreach ($types as $item => $label) {
      $item_value = $this->get($item)->getValue();
      if ($item_value !== NULL && $item_value !== '') {
        $empty = FALSE;
      }
    }

    return $empty;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $types = static::contactTypes();
    $properties = [];
    foreach ($types as $item => $label) {
      $properties[$item] = DataDefinition::create('string')
        ->setLabel($label);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $types = static::contactTypes();
    $columns = [];
    foreach ($types as $item => $label) {
      $columns += [
        $item => [
          'type' => 'varchar',
          'description' => $label,
          'length' => 255,
        ],
      ];
    }

    return [
      'columns' => $columns,
    ];
  }

}
