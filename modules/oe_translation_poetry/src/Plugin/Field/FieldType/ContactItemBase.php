<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the Contact Item Base class field type.
 */
abstract class ContactItemBase extends FieldItemBase {

  /**
   * Provides the field type specific values.
   *
   * @return array
   *   The field type values.
   */
  abstract protected static function contactValues();

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $empty = TRUE;
    $values = static::contactValues();
    foreach ($values as $item => $label) {
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
    $values = static::contactValues();
    $properties = [];
    foreach ($values as $item => $label) {
      $properties[$item] = DataDefinition::create('string')
        ->setLabel($label);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $values = static::contactValues();
    $columns = [];
    foreach ($values as $item => $label) {
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
