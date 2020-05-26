<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Represents a Poetry contact field item.
 */
interface ContactItemInterface extends FieldItemInterface {

  /**
   * Returns the contact types of this field type.
   *
   * @return array
   *   The contact type value labels keyed by their machine names.
   */
  public static function contactTypes(): array;

}
