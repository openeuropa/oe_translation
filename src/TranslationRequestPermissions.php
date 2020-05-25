<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_translation\Entity\TranslationRequestType;

/**
 * Provides dynamic permissions for different translation request types.
 */
class TranslationRequestPermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of translation request permissions.
   */
  public function translationRequestTypePermissions() {
    $perms = [];
    // Generate permissions for all translation request types.
    foreach (TranslationRequestType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of permissions for a given translation request type.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestType $type
   *   The translation request type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(TranslationRequestType $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return [
      "create $type_id translation request" => [
        'title' => $this->t('Create new %type_name translation request', $type_params),
      ],
      "edit $type_id translation request" => [
        'title' => $this->t('Edit any %type_name translation request', $type_params),
      ],
      "delete $type_id translation request" => [
        'title' => $this->t('Delete any %type_name translation request', $type_params),
      ],
    ];
  }

}
