<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Interface for the 'Translation request log' entity.
 */
interface TranslationRequestLogInterface extends ContentEntityInterface {

  /**
   * Info message type.
   */
  const INFO = 'info';

  /**
   * Error message type.
   */
  const ERROR = 'error';

  /**
   * Gets the translation request log creation timestamp.
   *
   * @return string
   *   Creation timestamp of the translation request log.
   */
  public function getCreatedTime(): string;

  /**
   * Sets the translation request log creation timestamp.
   *
   * @param int $timestamp
   *   The translation request log creation timestamp.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestLogInterface
   *   The called translation request log entity.
   */
  public function setCreatedTime(int $timestamp): TranslationRequestLogInterface;

  /**
   * Gets the translation request log message.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public function getMessage(): TranslatableMarkup;

  /**
   * Sets the translation request log message.
   *
   * @param string $message
   *   The message.
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestLogInterface
   *   The called translation request log entity.
   */
  public function setMessage(string $message): TranslationRequestLogInterface;

  /**
   * Gets the message type.
   *
   * @return int
   *   Message type.
   */
  public function getType(): string;

  /**
   * Sets the message type.
   *
   * @param string $type
   *   The message type (has to be one of the constants).
   *
   * @return \Drupal\oe_translation\Entity\TranslationRequestLogInterface
   *   The called translation request log entity.
   */
  public function setType(string $type): TranslationRequestLogInterface;

}
