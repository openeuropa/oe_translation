<?php

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the Contact Widget base class field widget.
 */
abstract class ContactWidgetBase extends WidgetBase {

  /**
   * Provides the field type specific elements.
   *
   * @return array
   *   The field type elements.
   */
  abstract protected static function contactElements();

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $elements = static::contactElements();
    foreach ($elements as $item => $title) {
      $element[$item] = [
        '#type' => 'textfield',
        '#title' => $title,
        '#default_value' => isset($items[$delta]->$item) ? $items[$delta]->$item : NULL,
      ];
    }

    return $element;
  }

}
