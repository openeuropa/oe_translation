<?php

declare(strict_types=1);

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
   * The mapping applies to both published and validated versions.
   *
   * This is the default.
   */
  public const SCOPE_BOTH = 0;

  /**
   * The mapping applies only to the published version.
   *
   * This can happen if we have a published version mapped to a previous version
   * but we also have a new major (validated) which receives and syncs a
   * translation. At that point, we cannot remove the mapping yet, but we
   * indicate that for the validated version it should not apply. When the
   * entity gets published, the mapping gets removed entirely.
   */
  public const SCOPE_PUBLISHED = 1;

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

    // 0 means that it applies for both published and potential validated next
    // version. 1 means it applies only for the published version.
    $properties['scope'] = DataDefinition::create('integer')
      ->setLabel(t('Scope'))
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

    $schema['columns']['scope'] = [
      'type' => 'int',
      'description' => 'The scope the mapping applies for.',
      'length' => 1,
    ];

    return $schema;
  }

}
