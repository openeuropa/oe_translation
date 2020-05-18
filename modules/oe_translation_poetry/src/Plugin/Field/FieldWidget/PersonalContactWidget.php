<?php

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'Personal Contact' field widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_poetry_personal_contact_widget",
 *   label = @Translation("Personal Contact Widget"),
 *   field_types = {"oe_translation_poetry_personal_contact"},
 * )
 */
class PersonalContactWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author'),
      '#default_value' => isset($items[$delta]->author) ? $items[$delta]->author : NULL,
    ];
    $element['secretary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secretary'),
      '#default_value' => isset($items[$delta]->secretary) ? $items[$delta]->secretary : NULL,
    ];
    $element['contact'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Contact'),
      '#default_value' => isset($items[$delta]->contact) ? $items[$delta]->contact : NULL,
    ];
    $element['responsible'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Responsible'),
      '#default_value' => isset($items[$delta]->responsible) ? $items[$delta]->responsible : NULL,
    ];

    return $element;
  }

}
