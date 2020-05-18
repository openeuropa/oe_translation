<?php

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'Organisational Contact' field widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_poetry_organisation_contact_widget",
 *   label = @Translation("Organisational Contact Widget"),
 *   field_types = {"oe_translation_poetry_organisation_contact"},
 * )
 */
class OrganisationalContactWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['responsible'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Responsible'),
      '#default_value' => isset($items[$delta]->responsible) ? $items[$delta]->responsible : NULL,
    ];
    $element['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author'),
      '#default_value' => isset($items[$delta]->author) ? $items[$delta]->author : NULL,
    ];
    $element['requester'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Requester'),
      '#default_value' => isset($items[$delta]->requester) ? $items[$delta]->requester : NULL,
    ];

    return $element;
  }

}
