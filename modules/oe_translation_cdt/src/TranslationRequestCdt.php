<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_remote\RemoteTranslationRequestEntityTrait;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * A CDT bundle class for oe_translation_request entities.
 */
final class TranslationRequestCdt extends TranslationRequest implements TranslationRequestCdtInterface {
  use RemoteTranslationRequestEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getCdtId(): ?string {
    return $this->get('cdt_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCdtId(string $value): TranslationRequestCdtInterface {
    $this->set('cdt_id', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStatusFromCdt(string $cdt_status): TranslationRequestCdtInterface {
    $target_status = match($cdt_status) {
      'COMP' => TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED,
      'CANC' => TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED,
      default => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    };
    $this->setRequestStatus($target_status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTargetLanguageStatusFromCdt(string $langcode, string $cdt_status): void {
    $target_status = match($cdt_status) {
      'CMP' => TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW,
      default => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    };
    $this->updateTargetLanguageStatus($langcode, $target_status);
  }

  /**
   * {@inheritdoc}
   */
  public function getComments(): string {
    return $this->get('comments')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setComments(string $value): TranslationRequestCdtInterface {
    $this->set('comments', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfidentiality(): string {
    return $this->get('confidentiality')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfidentiality(string $value): TranslationRequestCdtInterface {
    $this->set('confidentiality', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getContactUsernames(): array {
    $contacts = [];
    foreach ($this->get('contact_usernames')->getValue() as $value) {
      $contacts[] = $value['value'];
    }
    return $contacts;
  }

  /**
   * {@inheritdoc}
   */
  public function setContactUsernames(array $values): TranslationRequestCdtInterface {
    $this->set('contact_usernames', $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeliverTo(): array {
    $contacts = [];
    foreach ($this->get('deliver_to')->getValue() as $value) {
      $contacts[] = $value['value'];
    }
    return $contacts;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeliverTo(array $values): TranslationRequestCdtInterface {
    $this->set('deliver_to', $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCorrelationId(): string {
    return $this->get('correlation_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCorrelationId(string $value): TranslationRequestCdtInterface {
    $this->set('correlation_id', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDepartment(): string {
    return $this->get('department')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDepartment(string $value): TranslationRequestCdtInterface {
    $this->set('department', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoneNumber(): string {
    return $this->get('phone_number')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPhoneNumber(string $value): TranslationRequestCdtInterface {
    $this->set('phone_number', $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPriority(): string {
    return $this->get('priority')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPriority(string $value): TranslationRequestCdtInterface {
    $this->set('priority', $value);
    return $this;
  }

}