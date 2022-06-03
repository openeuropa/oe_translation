<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a translation request entity type.
 */
interface TranslationRequestInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, EntityPublishedInterface {

  /**
   * The statuses of a translation request.
   */
  const STATUS_DRAFT = 'draft';
  const STATUS_REVIEW = 'review';
  const STATUS_ACCEPTED = 'accepted';
  const STATUS_SYNCHRONISED = 'synchronized';

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

  /**
   * Gets the decoded json data that should be translated.
   *
   * @return array
   *   The decoded json.
   */
  public function getData(): array;

  /**
   * Sets the json encoded data that should be translated.
   *
   * @param array $data
   *   The data to be translated.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestInterface
   *   The called translation request entity.
   */
  public function setData(array $data): TranslationRequestInterface;

  /**
   * Retrieves the Translation request log entities.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestLogInterface[]
   *   Array of Translation request log entities.
   */
  public function getLogMessages(): array;

  /**
   * Adds a new log message.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestLogInterface $log
   *   The translations request log entity to be added.
   */
  public function addLogMessage(TranslationRequestLogInterface $log);

  /**
   * Creates an operations links for the entity.
   *
   * @return array
   *   The operations links.
   */
  public function getOperationsLinks(): array;

  /**
   * Generates the operation link to create a new request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to generate the link.
   * @param string $target_langcode
   *   The target link.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache
   *   The cacheable metadata from the context.
   *
   * @return array
   *   The link information.
   */
  public static function getCreateOperationLink(ContentEntityInterface $entity, string $target_langcode, CacheableMetadata $cache): array;

}
