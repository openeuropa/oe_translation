<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_remote\RemoteTranslationRequestEntityTrait;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Bundle class for the ePoetry TranslationRequest entity.
 */
class TranslationRequestEpoetry extends TranslationRequest implements TranslationRequestEpoetryInterface {

  use RemoteTranslationRequestEntityTrait;

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

}
