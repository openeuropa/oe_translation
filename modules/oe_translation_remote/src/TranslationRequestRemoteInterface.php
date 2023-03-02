<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote;

use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface;

/**
 * Interface for remote translation request entities.
 */
interface TranslationRequestRemoteInterface extends TranslationRequestInterface {

  /**
   * The statuses of a translation request language.
   *
   * These are the statuses all remote translators can have. Each remote
   * translator type may have their own specific statuses that can come
   * between these.
   */
  const STATUS_LANGUAGE_ACTIVE = 'Active';
  const STATUS_LANGUAGE_REVIEW = 'Review';
  const STATUS_LANGUAGE_ACCEPTED = 'Accepted';
  const STATUS_LANGUAGE_SYNCHRONISED = 'Synchronised';

  /**
   * The statuses of a translation request as a whole.
   *
   * These are the mandatory statuses a remote request can have. Each remote
   * translator can, however, provide its own extra statuses.
   *
   * ACTIVE: the request is sent to the provider.
   * TRANSLATED: all the language translations have arrived and the provider's
   * job is done.
   * FINISHED: all the language translations have been synced.
   * FAILED: the request failed upon initial send.
   * FAILED_FINISHED: the request failed upon initial send and was marked as
   * finished.
   */
  const STATUS_REQUEST_ACTIVE = 'Active';
  const STATUS_REQUEST_TRANSLATED = 'Translated';
  const STATUS_REQUEST_FINISHED = 'Finished';
  const STATUS_REQUEST_FAILED = 'Failed';
  const STATUS_REQUEST_FAILED_FINISHED = 'Failed & Finished';

  /**
   * Returns the request status.
   */
  public function getRequestStatus(): string;

  /**
   * Sets the request status.
   *
   * @param string $status
   *   The status.
   *
   * @return TranslationRequestRemoteInterface
   *   The current request.
   */
  public function setRequestStatus(string $status): TranslationRequestRemoteInterface;

  /**
   * Returns the translator provider.
   *
   * @return \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface
   *   The translator provider.
   */
  public function getTranslatorProvider(): RemoteTranslatorProviderInterface;

  /**
   * Returns the target languages.
   *
   * @return \Drupal\oe_translation\LanguageWithStatus[]
   *   The target languages
   */
  public function getTargetLanguages(): array;

  /**
   * Returns a given target language with its status.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return \Drupal\oe_translation\LanguageWithStatus|null
   *   The target language with its status.
   */
  public function getTargetLanguage(string $langcode): ?LanguageWithStatus;

  /**
   * Updates the status of a given target language.
   *
   * If the langcode doesn't exist, it gets set with that status.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $status
   *   The status.
   *
   * @return TranslationRequestRemoteInterface
   *   The current entity.
   */
  public function updateTargetLanguageStatus(string $langcode, string $status): TranslationRequestRemoteInterface;

  /**
   * Sets the data with the translated values for a given language.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $data
   *   The data.
   *
   * @return TranslationRequestRemoteInterface
   *   The current entity.
   */
  public function setTranslatedData(string $langcode, array $data): TranslationRequestRemoteInterface;

  /**
   * Returns all the translated data, keyed by langcode.
   *
   * @return array
   *   The translated data.
   */
  public function getTranslatedData(): array;

}
