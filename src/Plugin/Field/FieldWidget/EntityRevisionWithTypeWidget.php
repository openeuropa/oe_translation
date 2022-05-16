<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the EntityRevisionWithTypeItem widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_entity_revision_type_widget",
 *   label = @Translation("Entity revision with type widget"),
 *   field_types = {
 *     "oe_translation_entity_revision_type_item"
 *   }
 * )
 */
class EntityRevisionWithTypeWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element += [
      '#type' => 'fieldset',
    ];

    $element['entity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity ID'),
      '#default_value' => $items[$delta]->entity_id ?? NULL,
      '#required' => $element['#required'],
    ];
    $element['entity_revision_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Revision'),
      '#default_value' => $items[$delta]->entity_revision_id ?? NULL,
      '#required' => $element['#required'],
    ];
    $element['entity_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Type'),
      '#default_value' => $items[$delta]->entity_type ?? NULL,
      '#required' => $element['#required'],
    ];

    return $element;
  }

}
