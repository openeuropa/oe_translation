<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_local;

use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation\LanguageWithStatus;

/**
 * Bundle class for the local TranslationRequest entity.
 */
class TranslationRequestLocal extends TranslationRequest {

  /**
   * The statuses of a translation request language.
   */
  const STATUS_LANGUAGE_DRAFT = 'Draft';
  const STATUS_LANGUAGE_ACCEPTED = 'Accepted';
  const STATUS_LANGUAGE_SYNCHRONISED = 'Synchronised';

  /**
   * Returns the target language.
   *
   * @return \Drupal\oe_translation\LanguageWithStatus|null
   *   The target language.
   */
  public function getTargetLanguageWithStatus(): ?LanguageWithStatus {
    if ($this->get('target_language')->isEmpty()) {
      return NULL;
    }

    $values = $this->get('target_language')->first()->getValue();
    return new LanguageWithStatus($values['langcode'], $values['status']);
  }

  /**
   * Sets the target language with status.
   *
   * @param \Drupal\oe_translation\LanguageWithStatus $language_with_status
   *   The language with status.
   *
   * @return \Drupal\oe_translation_local\TranslationRequestLocal
   *   This entity.
   */
  public function setTargetLanguageWithStatus(LanguageWithStatus $language_with_status): TranslationRequestLocal {
    $this->set('target_language', $language_with_status);
    return $this;
  }

  /**
   * Sets the target language with a status.
   *
   * @param string $langcode
   *   The language code.
   * @param string $status
   *   The status.
   *
   * @return \Drupal\oe_translation_local\TranslationRequestLocal
   *   This entity.
   */
  public function setTargetLanguage(string $langcode, string $status = self::STATUS_LANGUAGE_DRAFT): TranslationRequestLocal {
    $this->set('target_language', [
      'langcode' => $langcode,
      'status' => $status,
    ]);
    return $this;
  }

  /**
   * Updates the target language with a status (draft).
   *
   * @param string $status
   *   The status.
   *
   * @return \Drupal\oe_translation_local\TranslationRequestLocal
   *   This entity.
   */
  public function updateTargetLanguageStatus(string $status = self::STATUS_LANGUAGE_DRAFT): TranslationRequestLocal {
    if ($this->get('target_language')->isEmpty()) {
      return $this;
    }

    $this->get('target_language')->first()->set('status', $status);
    return $this;
  }

}
