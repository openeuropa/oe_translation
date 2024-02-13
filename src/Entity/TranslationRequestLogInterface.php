<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Entity;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Interface for the 'Translation request log' entity.
 */
interface TranslationRequestLogInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Info message type.
   */
  const INFO = 'info';

  /**
   * Error message type.
   */
  const ERROR = 'error';

  /**
   * Warning message type.
   */
  const WARNING = 'warning';

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
   * @return \Drupal\Component\Render\FormattableMarkup|string
   *   The message.
   */
  public function getMessage(): MarkupInterface;

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
   * @return string
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
   *
   * @throws \Exception
   *   Trying to set a type that doesn't exist will throw an exception.
   */
  public function setType(string $type): TranslationRequestLogInterface;

  /**
   * Retrieves a list of available message types.
   *
   * @return array
   *   Array containing the message types.
   */
  public static function getMessageTypes(): array;

}
