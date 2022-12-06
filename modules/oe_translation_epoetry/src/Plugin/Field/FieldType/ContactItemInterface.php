<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Interface class for the Contact item field type.
 */
interface ContactItemInterface extends FieldItemInterface {

  /**
   * Returns the contact types of this field type.
   *
   * @return array
   *   The contact type values.
   */
  public static function contactTypes(): array;

}
