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
   * Creates and sets a log message.
   *
   * @param string $message
   *   The message.
   * @param array $variables
   *   The message variables.
   * @param string $type
   *   The type.
   */
  public function log(string $message, array $variables = [], string $type = TranslationRequestLogInterface::INFO): void;

  /**
   * Creates an operations links for the entity.
   *
   * @return array
   *   The operations links.
   */
  public function getOperationsLinks(): array;

}
