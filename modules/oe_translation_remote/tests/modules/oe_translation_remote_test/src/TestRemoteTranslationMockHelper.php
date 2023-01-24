<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote_test;

use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Helper class for mocking data to help with testing remote translations.
 */
class TestRemoteTranslationMockHelper {

  /**
   * Adds dummy translation request values for a given language.
   *
   * @param \Drupal\oe_translation_remote\TranslationRequestRemoteInterface $request
   *   The translation request.
   * @param string $langcode
   *   The langcode.
   * @param string|null $suffix
   *   An extra suffix to append to the translation.
   */
  public static function translateRequest(TranslationRequestRemoteInterface $request, string $langcode, ?string $suffix = NULL): void {
    $data = $request->getData();

    foreach ($data as $field => &$info) {
      if (!is_array($info)) {
        continue;
      }
      static::translateFieldData($info, $langcode, $suffix);
    }

    $request->setTranslatedData($langcode, $data);
    $request->updateTargetLanguageStatus($langcode, TranslationRequestTestRemote::STATUS_LANGUAGE_REVIEW);
  }

  /**
   * Recursively sets translated data to field values.
   *
   * @param array $data
   *   The data.
   * @param string $langcode
   *   The langcode.
   * @param string|null $suffix
   *   An extra suffix to append to the translation.
   */
  protected static function translateFieldData(array &$data, string $langcode, ?string $suffix = NULL): void {
    if (!isset($data['#text'])) {
      foreach ($data as $field => &$info) {
        if (!is_array($info)) {
          continue;
        }
        static::translateFieldData($info, $langcode, $suffix);
      }

      return;
    }

    if (isset($data['#translate']) && $data['#translate'] === FALSE) {
      return;
    }

    // Check whether this is a new translation or not by checking for a
    // stored translation for the field.
    if (isset($data['#translation'])) {
      $data['#translation']['#text'] = $data['#translation']['#text'] . ' OVERRIDDEN';
      return;
    }

    $append = $suffix ? $langcode . ' - ' . $suffix : $langcode;
    $data['#translation']['#text'] = $data['#text'] . ' - ' . $append;
  }

}
