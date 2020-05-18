<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'Organisational Contact' field type.
 *
 * @FieldType(
 *   id = "oe_translation_poetry_organisation_contact",
 *   label = @Translation("Organisational Contact"),
 *   category = @Translation("OpenEuropa"),
 *   default_widget = "oe_translation_poetry_organisation_contact_widget",
 *   default_formatter = "oe_translation_poetry_organisation_contact_formatter"
 * )
 */
class OrganisationalContactItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $responsible = $this->get('responsible')->getValue();
    $author = $this->get('author')->getValue();
    $requester = $this->get('requester')->getValue();

    return ($responsible === NULL || $responsible === '') &&
      ($author === NULL || $author === '') &&
      ($requester === NULL || $requester === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['responsible'] = DataDefinition::create('string')
      ->setLabel(t('Responsible'));
    $properties['author'] = DataDefinition::create('string')
      ->setLabel(t('Author'));
    $properties['requester'] = DataDefinition::create('string')
      ->setLabel(t('Requester'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'responsible' => [
          'type' => 'varchar',
          'description' => 'Responsible.',
          'length' => '255',
        ],
        'author' => [
          'type' => 'varchar',
          'description' => 'Author.',
          'length' => 255,
        ],
        'requester' => [
          'type' => 'varchar',
          'description' => 'Requester.',
          'length' => '255',
        ],
      ],
    ];

    return $schema;
  }

}
