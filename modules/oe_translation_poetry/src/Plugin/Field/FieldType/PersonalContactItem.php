<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

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
class PersonalContactItem extends ContactItemBase {

  /**
   * {@inheritdoc}
   */
  protected static function contactValues() {
    return [
      'author' => t('Author'),
      'secretary' => t('Secretary'),
      'contact' => t('Contact'),
      'responsible' => t('Responsible'),
    ];
  }

}
