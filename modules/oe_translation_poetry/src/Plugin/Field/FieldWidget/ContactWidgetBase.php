<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for contact field type widgets.
 */
abstract class ContactWidgetBase extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $types = $items[$delta]->contactTypes();
    $element['#type'] = 'fieldset';
    foreach ($types as $type => $label) {
      $element[$type] = [
        '#type' => 'textfield',
        '#title' => $label,
        '#default_value' => $items[$delta]->{$type} ?? NULL,
        '#required' => $element['#required'],
      ];
    }

    return $element;
  }

}
