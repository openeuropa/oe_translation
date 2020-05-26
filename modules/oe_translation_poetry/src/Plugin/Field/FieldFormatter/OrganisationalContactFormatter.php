<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'Organisational Contact' formatter.
 *
 * @FieldFormatter(
 *   id = "oe_translation_poetry_organisation_contact_formatter",
 *   label = @Translation("Organisational Contact Formatter"),
 *   field_types = {"oe_translation_poetry_organisation_contact"}
 * )
 */
class OrganisationalContactFormatter extends FormatterBase {

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
          $item->responsible,
          $item->author,
          $item->requester,
        ]),
      ];
    }

    return $elements;
  }

}
