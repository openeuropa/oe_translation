<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Personal Contact' formatter.
 *
 * @FieldFormatter(
 *   id = "oe_translation_poetry_personal_contact_formatter",
 *   label = @Translation("Personal Contact Formatter"),
 *   field_types = {"oe_translation_poetry_personal_contact"}
 * )
 */
class PersonalContactFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    if (count($items) === 0) {
      return [];
    }

    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#markup' => implode('<br />', [
          $item->author,
          $item->secretary,
          $item->contact,
          $item->responsible,
        ]),
      ];
    }

    return $elements;
  }

}
