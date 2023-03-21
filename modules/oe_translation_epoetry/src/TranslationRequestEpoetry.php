<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_remote\RemoteTranslationRequestEntityTrait;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Bundle class for the ePoetry TranslationRequest entity.
 */
class TranslationRequestEpoetry extends TranslationRequest implements TranslationRequestEpoetryInterface {

  use RemoteTranslationRequestEntityTrait {
    getLanguageStatusDescription as traitGetLanguageStatusDescription;
  }

  /**
   * {@inheritdoc}
   */
  public function isAutoAccept(): bool {
    return (bool) $this->get('auto_accept')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoAccept(bool $value): TranslationRequestEpoetryInterface {
    $this->set('auto_accept', $value);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isAutoSync(): bool {
    return (bool) $this->get('auto_sync')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAutoSync(bool $value): TranslationRequestEpoetryInterface {
    $this->set('auto_sync', $value);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeadline(): DrupalDateTime {
    return $this->get('deadline')->date;
  }

  /**
   * {@inheritdoc}
   */
  public function setDeadline(DrupalDateTime $date): TranslationRequestEpoetryInterface {
    $this->set('deadline', $date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT));

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTargetLanguageAcceptedDeadline(string $langcode, \DateTimeInterface $date): TranslationRequestEpoetryInterface {
    $values = $this->get('accepted_deadline')->getValue();
    foreach ($values as &$value) {
      if ($value['langcode'] === $langcode) {
        $value['date_value'] = $date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
        $this->set('accepted_deadline', $values);
        return $this;
      }
    }

    $values[] = [
      'langcode' => $langcode,
      'date_value' => $date->format(DateTimeItemInterface::DATE_STORAGE_FORMAT),
    ];

    $this->set('accepted_deadline', $values);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguageAcceptedDeadline(string $langcode): ?DrupalDateTime {
    foreach ($this->get('accepted_deadline') as $item) {
      if ($item->langcode === $langcode) {
        return $item->date;
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getContacts(): array {
    $contacts = [];
    foreach ($this->get('contacts')->getValue() as $contact) {
      $contacts[$contact['contact_type']] = $contact['contact'];
    }
    return $contacts;
  }

  /**
   * {@inheritdoc}
   */
  public function setContacts(array $contacts): TranslationRequestEpoetryInterface {
    $values = [];
    foreach ($contacts as $type => $contact) {
      $values[] = [
        'contact_type' => $type,
        'contact' => $contact,
      ];
    }
    $this->set('contacts', $values);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage(): ?string {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(string $message): TranslationRequestEpoetryInterface {
    $this->set('message', $message);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestId(array $request_id): TranslationRequestEpoetryInterface {
    $this->set('request_id', $request_id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestId(bool $formatted = FALSE): string|array {
    if ($this->get('request_id')->isEmpty()) {
      return [];
    }

    $values = array_filter($this->get('request_id')->first()->getValue(), function ($value) {
      return !is_array($value);
    });
    return $formatted ? $this->get('request_id')->first()->toReference($values) : $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getEpoetryRequestStatus(): ?string {
    return $this->get('epoetry_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setEpoetryRequestStatus(string $status): TranslationRequestRemoteInterface {
    $this->set('epoetry_status', $status);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEpoetryRequestStatusDescription(string $status): TranslatableMarkup {
    switch ($status) {
      case TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT:
        return t('The request has been sent to ePoetry and they need to accept or reject it.');

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED:
        return t('The translation request has been accepted by ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED:
        return t('The translation request has been rejected by ePoetry. Please check the request logs for the reason why it was rejected.');

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED:
        return t('The translation request has been cancelled by ePoetry. You cannot reopen this request but you can make a new one.');

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED:
        return t('The translation request has been suspended by ePoetry. This can be a temporary measure and the request can be unsuspended by ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED:
        return t('The translation request has been executed by ePoetry. This means they have dispatched the translations for all the languages.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageStatusDescription(string $status, string $langcode): TranslatableMarkup {
    switch ($status) {
      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_EPOETRY_ACCEPTED:
        // For the Accepted language, we need to check if it's actually the
        // ePoetry Accepted or the local Accepted.
        $review = Url::fromRoute('entity.oe_translation_request.remote_translation_review', [
          'oe_translation_request' => $this->id(),
          'language' => $langcode,
        ]);

        if ($review->access()) {
          // If we can review, it means it's been accepted on our side and
          // not in ePoetry.
          return $this->traitGetLanguageStatusDescription($status, $langcode);
        }
        // Otherwise, it means it's been accepted in ePoetry.
        return t('The translation in this language has been accepted in ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING:
        return t('The content is being translated in ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_READY:
        return t('The translation is ready and will be shortly sent by ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT:
        return t('The translation has been sent by ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CLOSED:
        return t('The translation has been sent and the task has been closed by ePoetry.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED:
        return t('The translation for this language has been cancelled by ePoetry. It cannot be reopened.');

      case TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SUSPENDED:
        return t('The translation for this language has been suspended by ePoetry. This can be a temporary measure and it can be unsuspended by ePoetry.');
    }

    return $this->traitGetLanguageStatusDescription($status, $langcode);
  }

}
