<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'oe_translation_language_with_date' field type.
 *
 * This field stores a language code with a date value.
 *
 * @FieldType(
 *   id = "oe_translation_language_with_date",
 *   label = @Translation("Language with date"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "string_textfield",
 *   default_formatter = "string"
 * )
 */
class LanguageWithDateItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'datetime_type' => 'date',
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $langcode = $this->get('langcode')->getValue();
    $date = $this->get('date_value')->getValue();
    return $langcode === NULL || $langcode === '' || $date === NULL || $date === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(t('Language code'))
      ->setRequired(TRUE);

    $properties['date_value'] = DataDefinition::create('datetime_iso8601')
      ->setLabel(new TranslatableMarkup('Date value'))
      ->setRequired(TRUE);

    $properties['date'] = DataDefinition::create('any')
      ->setLabel(new TranslatableMarkup('Computed date'))
      ->setDescription(new TranslatableMarkup('The computed DateTime object.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\datetime\DateTimeComputed')
      ->setSetting('date source', 'date_value');

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
      'date_value' => [
        'description' => 'The date value.',
        'type' => 'varchar',
        'length' => 20,
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($property_name, $notify = TRUE) {
    // Enforce that the computed date is recalculated.
    if ($property_name == 'date_value') {
      $this->date = NULL;
    }
    parent::onChange($property_name, $notify);
  }

}
