<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the "EntityRevisionWithTypeItem" field type.
 *
 * @FieldType(
 *   id = "oe_translation_entity_revision_type_item",
 *   label = @Translation("Entity revision with type item"),
 *   category = @Translation("OpenEuropa"),
 *   default_formatter = "oe_translation_entity_revision_type_item_formatter",
 *   default_widget = "oe_translation_entity_revision_type_item_widget"
 * )
 */
class EntityRevisionWithTypeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $entity_id = $this->get('entity_id')->getValue();
    $entity_revision = $this->get('entity_revision')->getValue();
    $entity_type = $this->get('entity_type')->getValue();

    return ($entity_id === NULL || $entity_id === '') &&
      ($entity_revision === NULL || $entity_revision === '') &&
      ($entity_type === NULL || $entity_type === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['entity_id'] = DataDefinition::create('string')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('The entity id.'))
      ->setRequired(TRUE);
    $properties['entity_revision'] = DataDefinition::create('string')
      ->setLabel(t('Entity Revision'))
      ->setDescription(t('The entity revision id.'))
      ->setRequired(TRUE);
    $properties['entity_type'] = DataDefinition::create('string')
      ->setLabel(t('Entity Type'))
      ->setDescription(t('The entity type.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'entity_id' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Entity ID.',
        'length' => 255,
      ],
      'entity_revision' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Entity Revision.',
        'length' => 255,
      ],
      'entity_type' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Entity Type.',
        'length' => 255,
      ],
    ];

    $schema = [
      'columns' => $columns,
    ];

    return $schema;
  }

}
