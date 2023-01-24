<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Interface for the ePoetry translation request bundle class.
 */
interface TranslationRequestEpoetryInterface extends TranslationRequestRemoteInterface {

  /**
   * The statuses of a translation request language.
   *
   * These are specific ePoetry statuses that can exist during the course of
   * a translation cycle. Not all may be used.
   *
   * When a new request is made, the response status we get for the language is
   * "SenttoDGT", like the entire request. For us, this will be the global
   * status STATUS_REQUEST_ACTIVE.
   */
  const STATUS_LANGUAGE_EPOETRY_ACCEPTED = 'Accepted';
  const STATUS_LANGUAGE_ONGOING = 'Ongoing';
  const STATUS_LANGUAGE_READY = 'ReadyToBeSent';
  const STATUS_LANGUAGE_SENT = 'Sent';
  const STATUS_LANGUAGE_CLOSED = 'Closed';
  const STATUS_LANGUAGE_CANCELLED = 'Cancelled';
  const STATUS_LANGUAGE_SUSPENDED = 'Suspended';

  /**
   * The statuses of a translation request as a whole.
   *
   * These are specific ePoetry statuses that can exist during the course of
   * a translation cycle. Not all may be used but these are the ones the system
   * provides.
   */
  const STATUS_REQUEST_SENT = 'SenttoDGT';
  const STATUS_REQUEST_ACCEPTED = 'Accepted';
  const STATUS_REQUEST_REJECTED = 'Rejected';
  const STATUS_REQUEST_CANCELLED = 'Cancelled';
  const STATUS_REQUEST_SUSPENDED = 'Suspended';
  const STATUS_REQUEST_EXECUTED = 'Executed';

  /**
   * Returns whether the request is configured to auto-accept.
   *
   * @return bool
   *   Whether the request is configured to auto-accept.
   */
  public function isAutoAccept(): bool;

  /**
   * Sets the auto-accept value.
   *
   * @param bool $value
   *   The auto-accept value.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setAutoAccept(bool $value): TranslationRequestEpoetryInterface;

  /**
   * Returns whether the request is configured to auto-sync.
   *
   * @return bool
   *   Whether the request is configured to auto-sync.
   */
  public function isAutoSync(): bool;

  /**
   * Sets the auto-sync value.
   *
   * @param bool $value
   *   The auto-sync value.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setAutoSync(bool $value): TranslationRequestEpoetryInterface;

  /**
   * Returns the request deadline.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The deadline.
   */
  public function getDeadline(): DrupalDateTime;

  /**
   * Sets the request deadline.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The deadline.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setDeadline(DrupalDateTime $date): TranslationRequestEpoetryInterface;

  /**
   * Returns the accepted deadline.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The accepted deadline.
   */
  public function getAcceptedDeadline(): ?DrupalDateTime;

  /**
   * Sets the accepted deadline.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The accepted deadline.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setAcceptedDeadline(DrupalDateTime $date): TranslationRequestEpoetryInterface;

  /**
   * Returns the contacts.
   *
   * @return array
   *   The contacts
   */
  public function getContacts(): array;

  /**
   * Sets the contacts.
   *
   * @param array $contacts
   *   The contacts.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setContacts(array $contacts): TranslationRequestEpoetryInterface;

  /**
   * Returns the message.
   *
   * @return string|null
   *   The message.
   */
  public function getMessage(): ?string;

  /**
   * Sets the message.
   *
   * @param string $message
   *   The message.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setMessage(string $message): TranslationRequestEpoetryInterface;

  /**
   * Sets the request ID values.
   *
   * @param array $request_id
   *   The request ID values.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The current request.
   */
  public function setRequestId(array $request_id): TranslationRequestEpoetryInterface;

  /**
   * Gets the request ID values.
   *
   * @param bool $formatted
   *   Whether to format them into a string.
   *
   * @return string|array
   *   The request ID values.
   */
  public function getRequestId(bool $formatted = FALSE): string|array;

  /**
   * Returns the ePoetry request status.
   */
  public function getEpoetryRequestStatus(): ?string;

  /**
   * Sets the ePoetry request status.
   *
   * @param string $status
   *   The status.
   *
   * @return \Drupal\oe_translation_remote\TranslationRequestRemoteInterface
   *   The current request.
   */
  public function setEpoetryRequestStatus(string $status): TranslationRequestRemoteInterface;

}
