<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote;

use Drupal\Component\Serialization\Json;
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
  public function getTranslatedData(): array {
    $values = $this->get('translated_data')->getValue();
    $data = [];
    foreach ($values as $value) {
      $data[$value['langcode']] = Json::decode($value['data']);
    }

    return $data;
  }

}
