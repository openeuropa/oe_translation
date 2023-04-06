<?php

namespace Drupal\oe_translation\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'poetry_request_id_widget' field widget.
 *
 * @todo Remove after version 2.x was deployed.
 *
 * @FieldWidget(
 *   id = "poetry_request_id_widget",
 *   label = @Translation("Poetry Request ID"),
 *   field_types = {"poetry_request_id"},
 * )
 */
class PoetryRequestIdWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['poetry_request_id'] = [
      '#type' => 'details',
      '#title' => $element['#title'],
      '#description' => $element['#description'],
      '#open' => TRUE,
    ];

    $element['poetry_request_id']['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code'),
      '#default_value' => $items[$delta]->code ?? NULL,
    ];

    $element['poetry_request_id']['year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('year'),
      '#default_value' => $items[$delta]->year ?? NULL,
    ];

    $element['poetry_request_id']['number'] = [
      '#type' => 'number',
      '#title' => $this->t('Number'),
      '#default_value' => $items[$delta]->number ?? NULL,
    ];

    $element['poetry_request_id']['version'] = [
      '#type' => 'number',
      '#title' => $this->t('Version'),
      '#default_value' => $items[$delta]->version ?? NULL,
    ];

    $element['poetry_request_id']['part'] = [
      '#type' => 'number',
      '#title' => $this->t('Part'),
      '#default_value' => $items[$delta]->part ?? NULL,
    ];

    $element['poetry_request_id']['product'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Product'),
      '#default_value' => $items[$delta]->product ?? NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      $id = $item['poetry_request_id'];
      $item = $id;
    }

    return $values;
  }

}
