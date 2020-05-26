<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a translation request entity type.
 */
interface TranslationRequestInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * Gets the translation request creation timestamp.
   *
   * @return string
   *   Creation timestamp of the translation request.
   */
  public function getCreatedTime(): string;

  /**
   * Sets the translation request creation timestamp.
   *
   * @param int $timestamp
   *   The translation request creation timestamp.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setCreatedTime(int $timestamp): TranslationRequestInterface;

  /**
   * Gets the translation request content entity.
   *
   * @return array
   *   The content entity values.
   */
  public function getContentEntity(): array;

  /**
   * Sets the translation request content entity.
   *
   * @param array $content_entity
   *   The content entity values.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setContentEntity(array $content_entity): TranslationRequestInterface;

  /**
   * Gets the translation request provider.
   *
   * @return string
   *   The translation provider.
   */
  public function getTranslationProvider(): string;

  /**
   * Sets the translation request provider.
   *
   * @param string $translation_provider
   *   The translation provider.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setTranslationProvider(string $translation_provider): TranslationRequestInterface;

  /**
   * Gets the translation request source language code.
   *
   * @return string
   *   The source language code.
   */
  public function getSourceLanguageCode(): string;

  /**
   * Sets the translation request source language code.
   *
   * @param string $source_language_code
   *   The translation request source language code.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setSourceLanguageCode(string $source_language_code): TranslationRequestInterface;

  /**
   * Gets the translation request target language codes.
   *
   * @return array
   *   The target language codes.
   */
  public function getTargetLanguageCodes(): array;

  /**
   * Sets the translation request target language codes.
   *
   * @param array $target_language_codes
   *   The translation request target language codes.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setTargetLanguageCodes(array $target_language_codes): TranslationRequestInterface;

  /**
   * Gets the translation request jobs.
   *
   * @return \Drupal\tmgmt\JobInterface
   *   The jobs.
   */
  public function getJobs(): JobInterface;

  /**
   * Sets the translation request jobs.
   *
   * @param \Drupal\tmgmt\JobInterface $jobs
   *   The translation request jobs.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setJobs(JobInterface $jobs): TranslationRequestInterface;

  /**
   * Gets the translation request auto accept translation flag.
   *
   * @return bool
   *   TRUE if translation will be auto accepted, FALSE otherwise.
   */
  public function hasAutoAcceptTranslations(): bool;

  /**
   * Sets the translation request auto-accept translations flag value.
   *
   * @param bool $value
   *   The boolean value.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setAutoAcceptTranslations(bool $value): TranslationRequestInterface;

  /**
   * Gets the translation request synchronization settings.
   *
   * @return array
   *   The synchronization settings.
   */
  public function getTranslationSync(): array;

  /**
   * Sets the translation request synchronization settings.
   *
   * @param array $translation_synchronisation
   *   The synchronization settings.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setTranslationSync(array $translation_synchronisation): TranslationRequestInterface;

  /**
   * Gets the translation request upstream translation flag.
   *
   * @return bool
   *   TRUE if incoming translation should be upstreamed, FALSE otherwise.
   */
  public function hasUpstreamTranslation(): bool;

  /**
   * Sets the translation request upstream translation flag value.
   *
   * @param bool $value
   *   The boolean value.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setUpstreamTranslation(bool $value): TranslationRequestInterface;

  /**
   * Gets the translation request message for the provider.
   *
   * @return string
   *   The message for the provider.
   */
  public function getMessageForProvider(): string;

  /**
   * Sets the translation request message for the provider.
   *
   * @param string $message_for_provider
   *   The message for the provider.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setMessageForProvider(string $message_for_provider): TranslationRequestInterface;

  /**
   * Gets the translation request status.
   *
   * @return string
   *   The request status.
   */
  public function getRequestStatus(): string;

  /**
   * Sets the translation request status.
   *
   * @param string $request_status
   *   The status.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setRequestStatus(string $request_status): TranslationRequestInterface;

}