<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'oe_transalation_epoetry_contact_widget' field widget.
 *
 * @FieldWidget(
 *   id = "oe_transalation_epoetry_contact_widget",
 *   label = @Translation("Contact"),
 *   field_types = {"oe_transalation_epoetry_contact"},
 * )
 */
class ContactWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $types = $items[$delta]->contactTypes();
    $element['contact_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Contact type'),
      '#default_value' => $items[$delta]->contact_type ?? NULL,
      '#options' => array_combine($types, $types),
      '#empty_value' => '_none',
    ];
    $element['contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact'),
      '#default_value' => $items[$delta]->contact ?? NULL,
    ];

    return $element;
  }

}
