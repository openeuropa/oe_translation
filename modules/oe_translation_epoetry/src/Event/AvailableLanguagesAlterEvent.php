<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Event;

/**
 * Dispatched for altering the languages available for ePoetry translations.
 */
class AvailableLanguagesAlterEvent {

  /**
   * The event name.
   */
  const NAME = 'oe_translation_epoetry.available_languages_alter';

  /**
   * The available languages.
   *
   * @var \Drupal\Core\Language\LanguageInterface[]
   */
  protected $languages;

  /**
   * Constructs a AvailableLanguagesAlterEvent object.
   *
   * @param \Drupal\Core\Language\LanguageInterface[] $languages
   *   The available languages.
   */
  public function __construct(array $languages) {
    $this->languages = $languages;
  }

  /**
   * Gets the available languages.
   *
   * @return \Drupal\Core\Language\LanguageInterface[]
   *   The available languages.
   */
  public function getLanguages(): array {
    return $this->languages;
  }

  /**
   * Sets the available languages.
   *
   * @param \Drupal\Core\Language\LanguageInterface[] $languages
   *   The available languages.
   */
  public function setLanguages(array $languages): void {
    $this->languages = $languages;
  }

}
