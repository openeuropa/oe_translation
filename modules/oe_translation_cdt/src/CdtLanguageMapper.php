<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

/**
 * Maps the Drupal language to the configured CDT language.
 */
class CdtLanguageMapper {

  /**
   * Returns the CDT language code configured in the request translator.
   *
   * @param string $langcode
   *   The Drupal language code.
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request whose translator we need to check.
   *
   * @return string
   *   The CDT language code.
   */
  public static function getCdtLanguageCode(string $langcode, TranslationRequestCdtInterface $translation_request): string {
    // @todo Implement mapping configuration in the provider.
    return strtoupper($langcode);
  }

  /**
   * Returns the Drupal language code configured in the request translator.
   *
   * @param string $langcode
   *   The CDT language code.
   * @param \Drupal\oe_translation_cdt\TranslationRequestCdtInterface $translation_request
   *   The translation request whose translator we need to check.
   *
   * @return string
   *   The Drupal language code.
   */
  public static function getDrupalLanguageCode(string $langcode, TranslationRequestCdtInterface $translation_request): string {
    // @todo Implement mapping configuration in the provider.
    return strtolower($langcode);
  }

}
