<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Interface class for the Contact item field type.
 */
interface ContactItemInterface extends FieldItemInterface {

  /**
   * Value for the 'contact_type' setting: Requester.
   */
  const REQUESTER = 'Requester';

  /**
   * Value for the 'contact_type' setting: Author.
   */
  const AUTHOR = 'Author';

  /**
   * Value for the 'contact_type' setting: Recipient.
   */
  const RECIPIENT = 'Recipient';

  /**
   * Value for the 'contact_type' setting: Webmaster.
   */
  const WEBMASTER = 'Webmaster';

  /**
   * Value for the 'contact_type' setting: Editor.
   */
  const EDITOR = 'Editor';

  /**
   * Returns the contact types of this field type.
   *
   * @return array
   *   The contact type values.
   */
  public static function contactTypes(): array;

}
