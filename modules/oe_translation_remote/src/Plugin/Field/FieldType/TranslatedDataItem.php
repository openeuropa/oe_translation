<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'oe_translation_remote_translated_data' field type.
 *
 * This field type is used for storing the translated data per individual
 * language in a remote translation request. Each delta will reference the
 * language code and will contain the serialized data array containing
 * also the translation for that language. The data structure is the same as
 * in the entity data field.
 *
 * @FieldType(
 *   id = "oe_translation_remote_translated_data",
 *   label = @Translation("Translated data"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "string_textfield",
 *   default_formatter = "string"
 * )
 */
class TranslatedDataItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $langcode = $this->get('langcode')->getValue();
    $data = $this->get('data')->getValue();
    return $langcode === NULL || $langcode === '' || $data === NULL || $data === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(t('Language code'))
      ->setRequired(TRUE);

    $properties['data'] = DataDefinition::create('string')
      ->setLabel(t('The data containing translation values'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'langcode' => [
        'type' => 'varchar',
        'description' => 'The language code',
        'length' => 12,
      ],
      'data' => [
        'description' => 'The data with translation values',
        'type' => 'text',
        'size' => 'big',
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

}
