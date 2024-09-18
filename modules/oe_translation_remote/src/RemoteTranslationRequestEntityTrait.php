<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_translation\LanguageWithStatus;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface;

/**
 * Trait for remote translation request entity bundle classes.
 */
trait RemoteTranslationRequestEntityTrait {

  /**
   * {@inheritdoc}
   */
  public function label() {
    $entity = $this->getContentEntity();
    if (!$entity) {
      return parent::label();
    }

    return t('Translation request for @label', ['@label' => $entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestStatus(): string {
    return $this->get('request_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStatus(string $status): TranslationRequestRemoteInterface {
    $this->set('request_status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatorProvider(): RemoteTranslatorProviderInterface {
    return $this->get('translator_provider')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguages(): array {
    if ($this->get('target_languages')->isEmpty()) {
      return [];
    }

    $values = $this->get('target_languages')->getValue();
    $languages = [];
    foreach ($values as $value) {
      $languages[$value['langcode']] = new LanguageWithStatus($value['langcode'], $value['status']);
    }

    return $languages;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetLanguage(string $langcode): ?LanguageWithStatus {
    $languages = $this->getTargetLanguages();
    return $languages[$langcode] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function updateTargetLanguageStatus(string $langcode, string $status): TranslationRequestRemoteInterface {
    $values = $this->get('target_languages')->getValue();
    $update = FALSE;
    foreach ($values as &$value) {
      if ($value['langcode'] === $langcode) {
        $value['status'] = $status;
        $update = TRUE;
        break;
      }
    }

    if (!$update) {
      $values[] = [
        'langcode' => $langcode,
        'status' => $status,
      ];
    }

    $this->set('target_languages', $values);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setTranslatedData(string $langcode, array $data): TranslationRequestRemoteInterface {
    $values = $this->get('translated_data')->getValue();
    $update = FALSE;
    foreach ($values as &$value) {
      if ($value['langcode'] === $langcode) {
        $value['data'] = Json::encode($data);
        $update = TRUE;
        break;
      }
    }

    if (!$update) {
      $values[] = [
        'langcode' => $langcode,
        'data' => Json::encode($data),
      ];
    }

    $this->set('translated_data', $values);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeTranslatedData(string $langcode): TranslationRequestRemoteInterface {
    $values = $this->get('translated_data')->getValue();
    foreach ($values as $key => $value) {
      if ($value['langcode'] === $langcode) {
        unset($values[$key]);
        break;
      }
    }
    $this->set('translated_data', $values);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatedData(): array {
    $values = $this->get('translated_data')->getValue();
    $data = [];
    foreach ($values as $value) {
      $data[$value['langcode']] = Json::decode($value['data']);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestStatusDescription(string $status): TranslatableMarkup {
    switch ($status) {
      case TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED:
        return t('The request has been made to the external service.');

      case TranslationRequestRemoteInterface::STATUS_REQUEST_TRANSLATED:
        return t('The translations for all languages have arrived from the remote provider and at least one is still not synchronised with the content.');

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED:
        return t('All translations have been synchronised with the content.');

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED:
        return t('The request has failed but it has been manually marked as finished so a new request can be made to the remote provider.');

      case TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED:
        return t('The request has failed. You should mark it as "@value" in order to retry a new request.', ['@value' => TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageStatusDescription(string $status, string $langcode): TranslatableMarkup {
    switch ($status) {
      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED:
        return t('The language has been requested.');

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW:
        return t('The translation for this language has arrived and is ready for review.');

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED:
        return t('The translation for this language has been internally accepted and is ready for synchronisation.');

      case TranslationRequestRemoteInterface::STATUS_LANGUAGE_SYNCHRONISED:
        return t('The translation for this language has been synchronised.');
    }
  }

}
