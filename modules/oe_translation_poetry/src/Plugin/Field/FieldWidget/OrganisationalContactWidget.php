<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

/**
 * Defines the 'Organisational Contact' field widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_poetry_organisation_contact_widget",
 *   label = @Translation("Organisational Contact Widget"),
 *   field_types = {"oe_translation_poetry_organisation_contact"},
 * )
 */
class OrganisationalContactWidget extends ContactWidgetBase {}
