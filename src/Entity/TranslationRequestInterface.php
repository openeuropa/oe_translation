<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
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
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity.
   */
  public function getContentEntity(): ?ContentEntityInterface;

  /**
   * Sets the translation request content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setContentEntity(ContentEntityInterface $entity): TranslationRequestInterface;

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
