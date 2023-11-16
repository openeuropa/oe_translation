<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'oe_transalation_epoetry_contact' field type.
 *
 * @FieldType(
 *   id = "oe_transalation_epoetry_contact",
 *   label = @Translation("ePoetry contact"),
 *   category = @Translation("OE Translation"),
 *   default_widget = "oe_transalation_epoetry_contact_widget",
 *   default_formatter = "oe_transalation_epoetry_contact_formatter"
 * )
 */
class ContactItem extends FieldItemBase implements ContactItemInterface {

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    // We consider the field empty if all the values are empty.
    $contact_type = $this->get('contact_type')->getValue();
    $contact = $this->get('contact')->getValue();

    return ($contact_type === NULL || $contact_type === '') &&
      ($contact === NULL || $contact === '');
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['contact'] = DataDefinition::create('string')
      ->setLabel(t('Contact'))
      ->setDescription(t('The contact information about the ePoetry request.'))
      ->setRequired(TRUE);
    $properties['contact_type'] = DataDefinition::create('string')
      ->setLabel(t('Contact type'))
      ->setDescription(t('The type of the contact.'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $columns = [
      'contact' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Contact.',
        'length' => 255,
      ],
      'contact_type' => [
        'type' => 'varchar',
        'not null' => TRUE,
        'description' => 'Contact type.',
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
  public static function contactTypes(): array {
    return [
      self::AUTHOR => '',
      self::REQUESTER => '',
      self::RECIPIENT => t('The person who will receive notifications about the status of the request and the availability of the translations.'),
      self::EDITOR => t('The author of the page to be translated – the translator might contact this person if they have questions about the content of the page.'),
      self::WEBMASTER => t('The person that DGT can contact for information on the request and to negotiate the deadline.'),
    ];
  }

}
