<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Represents a request to be sent by the etrans client.
 */
class EtransRequest implements EtransRequestInterface {

  /**
   * The translation request.
   *
   * @var \Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   */
  protected $translationRequest;

  /**
   * The text to translate.
   *
   * @var string
   */
  protected string $text;

  /**
   * The source language.
   *
   * @var string
   */
  protected string $sourceLanguage;

  /**
   * The target languages.
   *
   * @var array
   */
  protected array $targetLanguages;

  /**
   * The translation domain.
   *
   * @var string
   */
  protected string $domain = 'GEN';

  /**
   * The delivery endpoint.
   *
   * @var string
   */
  protected string $deliveryEndpoint = '';

  /**
   * The failure endpoint.
   *
   * @var string
   */
  protected string $failureEndpoint = '';

  /**
   * Constructs a EtransRequest.
   *
   * @param \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $translationRequest
   *   The translation request.
   * @param string $text
   *   The text to translate.
   * @param string $sourceLanguage
   *   The source language.
   * @param array $targetLanguages
   *   The target languages.
   */
  public function __construct(TranslationRequestEtransInterface $translationRequest, string $text, string $sourceLanguage, array $targetLanguages) {
    $this->translationRequest = $translationRequest;
    $this->text = $text;
    $this->sourceLanguage = $sourceLanguage;
    $this->targetLanguages = $targetLanguages;
  }

  /**
   * {@inheritdoc}
   */
  public function setDomain(string $domain): void {
    $this->domain = $domain;
  }

  /**
   * {@inheritdoc}
   */
  public function toJson(): string {
    $array = [
      'callerInformation' => $this->getCallerInformation(),
      'documentToTranslate' => [
        'document' => [
          'content' => base64_encode($this->text),
          'format' => 'html',
        ],
      ],
      'outputFormat' => 'html',
      'preserveTags' => TRUE,
      'sourceLanguage' => $this->sourceLanguage,
      'targetLanguages' => $this->targetLanguages,
      'domain' => $this->domain,
      'deliveries' => [
        'http' => $this->getDeliveryEndpoint(),
      ],
      'notifications' => [
        'failure' => [
          'http' => $this->getFailureEndpoint(),
        ],
      ],
    ];

    return json_encode($array);
  }

  /**
   * {@inheritdoc}
   */
  public function getCallerInformation(): array {
    // As the external reference, send a unique token that can identify this
    // translation request.
    $token = $this->translationRequest->getSavedAccessToken();
    if (!$token) {
      $token = $this->translationRequest->generateAccessToken();
      $this->translationRequest->save();
    }

    return [
      'externalReference' => $token,
    ];
  }

  /**
   * Returns the delivery endpoint URL.
   *
   * @return string
   *   The URL.
   */
  protected function getDeliveryEndpoint(): string {
    if ($this->deliveryEndpoint != '') {
      return $this->deliveryEndpoint;
    }

    if (Settings::get('etrans.delivery_endpoint')) {
      // Used for testing.
      return Settings::get('etrans.delivery_endpoint');
    }

    return Url::fromRoute('oe_translation_etrans.delivery_callback')->setAbsolute()->toString();
  }

  /**
   * Returns the failure endpoint URL.
   *
   * @return string
   *   The URL.
   */
  protected function getFailureEndpoint(): string {
    if ($this->failureEndpoint != '') {
      return $this->failureEndpoint;
    }

    if (Settings::get('etrans.error_endpoint')) {
      // Used for testing.
      return Settings::get('etrans.error_endpoint');
    }
    return Url::fromRoute('oe_translation_etrans.error_callback')->setAbsolute()->toString();
  }

  /**
   * Sets the delivery endpoint.
   *
   * @param string $delivery_endpoint
   *   The endpoint.
   */
  public function setDeliveryEndpoint(string $delivery_endpoint): void {
    $this->deliveryEndpoint = $delivery_endpoint;
  }

  /**
   * Sets the failure endpoint.
   *
   * @param string $failure_endpoint
   *   The endpoint.
   */
  public function setFailureEndpoint(string $failure_endpoint): void {
    $this->failureEndpoint = $failure_endpoint;
  }

}
