<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_entity_revision_type_item_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the EntityRevisionWithTypeItem widget.
 *
 * @FieldWidget(
 *   id = "oe_translation_entity_revision_type_item_widget",
 *   label = @Translation("Entity revision with type item widget"),
 *   field_types = {
 *     "oe_translation_entity_revision_type_item"
 *   }
 * )
 */
class EntityRevisionWithTypeItemWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['entity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity ID'),
      '#default_value' => isset($items[$delta]->entity_id) ? $items[$delta]->entity_id : NULL,
    ];
    $element['entity_revision'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Revision'),
      '#default_value' => isset($items[$delta]->entity_revision) ? $items[$delta]->entity_revision : NULL,
    ];
    $element['entity_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Type'),
      '#default_value' => isset($items[$delta]->entity_type) ? $items[$delta]->entity_type : NULL,
    ];

    return $element;
  }

}
