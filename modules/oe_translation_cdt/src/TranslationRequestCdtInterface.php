<?php

namespace Drupal\oe_translation_cdt;

use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * An interface for CDT bundle class for oe_translation_request entities.
 */
interface TranslationRequestCdtInterface extends TranslationRequestRemoteInterface {

  /**
   * Returns the CDT ID.
   *
   * @return string|null
   *   The CDT ID including request year, or NULL if not set.
   */
  public function getCdtId(): ?string;

  /**
   * Sets the CDT ID.
   *
   * @param string $value
   *   The CDT ID including request year.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setCdtId(string $value): TranslationRequestCdtInterface;

  /**
   * Set the request status based on CDT code.
   *
   * @param string $cdt_status
   *   The CDT status code.
   */
  public function setRequestStatusFromCdt(string $cdt_status): TranslationRequestCdtInterface;

  /**
   * Set the language status based on CDT code.
   *
   * @param string $langcode
   *   The Drupal language code.
   * @param string $cdt_status
   *   The CDT status code.
   */
  public function updateTargetLanguageStatusFromCdt(string $langcode, string $cdt_status): void;

  /**
   * Returns the comments.
   *
   * @return string
   *   The comments.
   */
  public function getComments(): string;

  /**
   * Sets the comments.
   *
   * @param string $value
   *   The comments.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setComments(string $value): TranslationRequestCdtInterface;

  /**
   * Returns the confidentiality.
   *
   * @return string
   *   The confidentiality.
   */
  public function getConfidentiality(): string;

  /**
   * Sets the confidentiality.
   *
   * @param string $value
   *   The confidentiality.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setConfidentiality(string $value): TranslationRequestCdtInterface;

  /**
   * Returns the contacts.
   *
   * @return string[]
   *   The contacts.
   */
  public function getContactUsernames(): array;

  /**
   * Sets the contacts.
   *
   * @param string[] $values
   *   The contacts.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setContactUsernames(array $values): TranslationRequestCdtInterface;

  /**
   * Returns the "deliver to" contact list.
   *
   * @return string[]
   *   The contacts
   */
  public function getDeliverTo(): array;

  /**
   * Sets the "deliver to" contact list.
   *
   * @param string[] $values
   *   The contacts.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setDeliverTo(array $values): TranslationRequestCdtInterface;

  /**
   * Returns the correlation ID.
   *
   * @return string
   *   The correlation ID.
   */
  public function getCorrelationId(): string;

  /**
   * Sets the correlation ID.
   *
   * @param string $value
   *   The correlation ID.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setCorrelationId(string $value): TranslationRequestCdtInterface;

  /**
   * Gets the department.
   *
   * @return string
   *   The department.
   */
  public function getDepartment(): string;

  /**
   * Sets the department.
   *
   * @param string $value
   *   The department.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setDepartment(string $value): TranslationRequestCdtInterface;

  /**
   * Gets the phone number.
   *
   * @return string
   *   The phone number.
   */
  public function getPhoneNumber(): string;

  /**
   * Sets the phone number.
   *
   * @param string $value
   *   The phone number.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setPhoneNumber(string $value): TranslationRequestCdtInterface;

  /**
   * Gets the priority.
   *
   * @return string
   *   The priority.
   */
  public function getPriority(): string;

  /**
   * Sets the priority.
   *
   * @param string $value
   *   The priority.
   *
   * @return TranslationRequestCdtInterface
   *   The current request.
   */
  public function setPriority(string $value): TranslationRequestCdtInterface;

}
