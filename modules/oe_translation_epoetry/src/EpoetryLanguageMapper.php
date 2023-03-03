<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry;

/**
 * Maps the Drupal language to the configured ePoetry language.
 */
class EpoetryLanguageMapper {

  /**
   * Returns the ePoetry language code configured in the request translator.
   *
   * @param string $langcode
   *   The Drupal language code.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request whose translator we need to check.
   *
   * @return string
   *   The ePoetry language code.
   */
  public static function getEpoetryLanguageCode(string $langcode, TranslationRequestEpoetryInterface $translation_request): string {
    $configuration = $translation_request->getTranslatorProvider()->getProviderConfiguration();
    $mapping = $configuration['language_mapping'] ?? [];
    // In case it's not configured, we transform to uppercase the Drupal code.
    return $mapping[$langcode] ?? strtoupper($langcode);
  }

  /**
   * Returns the Drupal language code configured in the request translator.
   *
   * @param string $langcode
   *   The ePoetry language code.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request whose translator we need to check.
   *
   * @return string
   *   The Drupal language code.
   */
  public static function getDrupalLanguageCode(string $langcode, TranslationRequestEpoetryInterface $translation_request): string {
    $configuration = $translation_request->getTranslatorProvider()->getProviderConfiguration();
    $mapping = $configuration['language_mapping'] ?? [];
    foreach ($mapping as $drupal => $epoetry) {
      if ($epoetry == $langcode) {
        return $drupal;
      }
    }

    // In case it's not configured, we just lower the case of the ePoetry code.
    return strtolower($langcode);
  }

}
