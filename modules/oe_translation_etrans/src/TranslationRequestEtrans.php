<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation_remote\RemoteTranslationRequestEntityTrait;

/**
 * Translation request entity class for Etrans.
 */
class TranslationRequestEtrans extends TranslationRequest implements TranslationRequestEtransInterface {

  use RemoteTranslationRequestEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getRemoteId(): ?string {
    return $this->get('remote_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteId(string $remote_id): TranslationRequestEtransInterface {
    $this->set('remote_id', $remote_id);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSavedAccessToken(): ?string {
    return $this->get('etrans_access_token')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function generateAccessToken(): string {
    $data = [
      'request_id' => $this->id(),
      'entity_type' => $this->getContentEntity()->getEntityTypeId(),
      'entity_id' => $this->getContentEntity()->id(),
      'target_languages' => array_keys($this->getTargetLanguages()),
    ];

    $token = Crypt::hmacBase64(Json::encode($data), Settings::getHashSalt());
    $this->set('etrans_access_token', $token);
    return $token;
  }

}
