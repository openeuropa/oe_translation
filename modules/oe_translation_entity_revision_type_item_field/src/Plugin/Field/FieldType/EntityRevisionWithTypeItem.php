<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the "EntityRevisionWithType" field type.
 *
 * @FieldType(
 *   id = "oe_translation_entity_revision_type_item",
 *   label = @Translation("Entity revision with type"),
 *   category = @Translation("OpenEuropa"),
 *   default_formatter = "oe_translation_entity_revision_type_formatter",
 *   default_widget = "oe_translation_entity_revision_type_widget",
 *   list_class = "Drupal\oe_translation_entity_revision_type_item_field\EntityRevisionWithTypeItemList",
 * )
 */
class EntityRevisionWithTypeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $entity_id = $this->get('entity_id')->getValue();
    $entity_revision_id = $this->get('entity_revision_id')->getValue();
    $entity_type = $this->get('entity_type')->getValue();

    return ($entity_id === NULL || $entity_id === '') &&
      ($entity_revision_id === NULL || $entity_revision_id === '') &&
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

    $properties['entity_revision_id'] = DataDefinition::create('string')
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
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'entity_type' => [
        'EntityRevisionWithType' => [],
      ],
    ]);

    return $constraints;
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
      'entity_revision_id' => [
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

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return 'entity_id';
  }

}
