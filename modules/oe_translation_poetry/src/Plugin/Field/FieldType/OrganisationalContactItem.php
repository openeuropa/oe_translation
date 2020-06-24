<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

/**
 * Defines the 'Organisational Contact' field type.
 *
 * @FieldType(
 *   id = "oe_translation_poetry_organisation_contact",
 *   label = @Translation("Organisational Contact"),
 *   category = @Translation("OpenEuropa"),
 *   default_widget = "oe_translation_poetry_organisation_contact_widget",
 *   default_formatter = "oe_translation_poetry_organisation_contact_formatter",
 *   no_ui = TRUE
 * )
 */
class OrganisationalContactItem extends ContactItemBase {

  /**
   * {@inheritdoc}
   */
  public static function contactTypes(): array {
    return [
      'responsible' => t('Responsible'),
      'author' => t('Author'),
      'requester' => t('Requester'),
    ];
  }

}
