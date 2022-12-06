<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Contact' formatter.
 *
 * @FieldFormatter(
 *   id = "oe_transalation_epoetry_contact_formatter",
 *   label = @Translation("Contact"),
 *   field_types = {
 *     "oe_transalation_epoetry_contact"
 *   }
 * )
 */
class ContactFormatter extends FormatterBase {

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
          $item->contact_type,
          $item->contact,
        ]),
      ];
    }

    return $elements;
  }

}
