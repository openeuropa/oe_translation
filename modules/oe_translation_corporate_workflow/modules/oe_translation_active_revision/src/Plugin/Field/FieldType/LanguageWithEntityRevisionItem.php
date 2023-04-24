<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\oe_translation\Plugin\Field\FieldType\EntityRevisionWithTypeItem;

/**
 * Defines the 'oe_translation_language_with_entity_revision' field type.
 *
 * This field stores a language code and an entity revision.
 *
 * @FieldType(
 *   id = "oe_translation_language_with_entity_revision",
 *   label = @Translation("Language with entity revision"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "string_textfield",
 *   default_formatter = "string",
 *   list_class = "Drupal\oe_translation\Plugin\Field\EntityRevisionWithTypeItemList",
 *   no_ui = TRUE
 * )
 */
class LanguageWithEntityRevisionItem extends EntityRevisionWithTypeItem {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $langcode = $this->get('langcode')->getValue();
    return $langcode === NULL || $langcode === '' || parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['langcode'] = DataDefinition::create('string')
      ->setLabel(t('Language code'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['langcode'] = [
      'type' => 'varchar',
      'description' => 'The language code',
      'length' => 12,
    ];

    return $schema;
  }
}
