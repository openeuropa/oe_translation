<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\oe_translation\LanguageWithStatus;

/**
 * Defines the 'oe_translation_language_with_status' field type.
 *
 * This field stores a language code with a string status value.
 *
 * @FieldType(
 *   id = "oe_translation_language_with_status",
 *   label = @Translation("Language with status"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "string_textfield",
 *   default_formatter = "string"
 * )
 */
class LanguageWithStatusItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $langcode = $this->get('langcode')->getValue();
    $status = $this->get('status')->getValue();
    return $langcode === NULL || $langcode === '' || $status === NULL || $status === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(t('Language code'))
      ->setRequired(TRUE);

    $properties['status'] = DataDefinition::create('string')
      ->setLabel(t('Language status'))
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
      'status' => [
        'type' => 'varchar',
        'description' => 'The language status',
        'length' => 255,
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    if ($values instanceof LanguageWithStatus) {
      parent::setValue($this->createValue($values), $notify);
      return;
    }

    parent::setValue($values, $notify);
  }

  /**
   * Creates an individual value array from the object.
   *
   * @param \Drupal\oe_translation\LanguageWithStatus $language_with_status
   *   The object.
   *
   * @return array
   *   The value array.
   */
  protected function createValue(LanguageWithStatus $language_with_status): array {
    $value = [];
    $value['langcode'] = $language_with_status->getLangcode();
    $value['status'] = $language_with_status->getStatus();

    return $value;
  }

}
