<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

/**
 * Value object that holds a language code with a status value.
 */
class LanguageWithStatus {

  /**
   * The langcode.
   *
   * @var string
   */
  protected $langcode;

  /**
   * The status.
   *
   * @var string
   */
  protected $status;

  /**
   * Constructs a LanguageWithStatus object.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $status
   *   The status.
   */
  public function __construct(string $langcode, string $status) {
    $this->langcode = $langcode;
    $this->status = $status;
  }

  /**
   * Returns the langcode.
   *
   * @return string
   *   The langcode.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Returns the status.
   *
   * @return string
   *   The status.
   */
  public function getStatus(): string {
    return $this->status;
  }

}
