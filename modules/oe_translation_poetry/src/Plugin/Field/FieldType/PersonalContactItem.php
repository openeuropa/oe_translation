<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'Personal Contact' field type.
 *
 * @FieldType(
 *   id = "oe_translation_poetry_personal_contact",
 *   label = @Translation("Personal Contact"),
 *   category = @Translation("OpenEuropa"),
 *   default_widget = "oe_translation_poetry_personal_contact_widget",
 *   default_formatter = "oe_translation_poetry_personal_contact_formatter"
 * )
 */
class PersonalContactItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $author = $this->get('author')->getValue();
    $secretary = $this->get('secretary')->getValue();
    $contact = $this->get('contact')->getValue();
    $responsible = $this->get('responsible')->getValue();

    return ($author === NULL || $author === '') &&
      ($secretary === NULL || $secretary === '') &&
      ($contact === NULL || $contact === '') &&
      ($responsible === NULL || $responsible === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['author'] = DataDefinition::create('string')
      ->setLabel(t('Author'));
    $properties['secretary'] = DataDefinition::create('string')
      ->setLabel(t('Secretary'));
    $properties['contact'] = DataDefinition::create('string')
      ->setLabel(t('Contact'));
    $properties['responsible'] = DataDefinition::create('string')
      ->setLabel(t('Responsible'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'author' => [
          'type' => 'varchar',
          'description' => 'Author.',
          'length' => 255,
        ],
        'secretary' => [
          'type' => 'varchar',
          'description' => 'Secretary.',
          'length' => 255,
        ],
        'contact' => [
          'type' => 'varchar',
          'description' => 'Contact.',
          'length' => '255',
        ],
        'responsible' => [
          'type' => 'varchar',
          'description' => 'Responsible.',
          'length' => '255',
        ],
      ],
    ];

    return $schema;
  }

}
