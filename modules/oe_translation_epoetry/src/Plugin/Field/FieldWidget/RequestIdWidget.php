<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'epoetry_request_id_widget' field widget.
 *
 * @FieldWidget(
 *   id = "epoetry_request_id_widget",
 *   label = @Translation("ePoetry Request ID"),
 *   field_types = {"epoetry_request_id"},
 * )
 */
class RequestIdWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    $element['epoetry_request_id'] = [
      '#type' => 'details',
      '#title' => $element['#title'],
      '#description' => $element['#description'],
      '#open' => TRUE,
    ];

    $element['epoetry_request_id']['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code'),
      '#default_value' => $items[$delta]->code ?? NULL,
    ];

    $element['epoetry_request_id']['year'] = [
      '#type' => 'textfield',
      '#title' => $this->t('year'),
      '#default_value' => $items[$delta]->year ?? NULL,
    ];

    $element['epoetry_request_id']['number'] = [
      '#type' => 'number',
      '#title' => $this->t('Number'),
      '#default_value' => $items[$delta]->number ?? NULL,
    ];

    $element['epoetry_request_id']['version'] = [
      '#type' => 'number',
      '#title' => $this->t('Version'),
      '#default_value' => $items[$delta]->version ?? NULL,
    ];

    $element['epoetry_request_id']['part'] = [
      '#type' => 'number',
      '#title' => $this->t('Part'),
      '#default_value' => $items[$delta]->part ?? NULL,
    ];

    $element['epoetry_request_id']['service'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service type'),
      '#default_value' => $items[$delta]->service ?? NULL,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$item) {
      $id = $item['epoetry_request_id'];
      $item = $id;
    }

    return $values;
  }

}
