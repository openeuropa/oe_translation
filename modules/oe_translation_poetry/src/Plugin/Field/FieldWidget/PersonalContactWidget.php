<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

/**
 * Defines the 'Personal Contact' field widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_poetry_personal_contact_widget",
 *   label = @Translation("Personal Contact Widget"),
 *   field_types = {"oe_translation_poetry_personal_contact"},
 * )
 */
class PersonalContactWidget extends ContactWidgetBase {}
