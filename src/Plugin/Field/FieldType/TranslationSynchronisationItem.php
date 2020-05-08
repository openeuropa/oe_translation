<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\MapDataDefinition;

/**
 * Defines the 'Translation Synchronisation' field type.
 *
 * @FieldType(
 *   id = "oe_translation_translation_sync",
 *   label = @Translation("Translation Synchronisation"),
 *   category = @Translation("OpenEuropa"),
 *   default_widget = "oe_translation_translation_sync_widget",
 *   default_formatter = "oe_translation_translation_sync_formatter"
 * )
 */
class TranslationSynchronisationItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $type = $this->get('type')->getValue();
    $configuration = $this->get('configuration')->getValue();

    return ($type === NULL || $type === '') && ($configuration === NULL || $configuration === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Type'));
    $properties['configuration'] = MapDataDefinition::create()
      ->setLabel(t('Configuration'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'type' => [
        'type' => 'varchar_ascii',
        'description' => 'Type.',
        'length' => 255,
      ],
      'configuration' => [
        'type' => 'blob',
        'description' => 'Configuration.',
        'serialize' => TRUE,
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'type';
  }

}
