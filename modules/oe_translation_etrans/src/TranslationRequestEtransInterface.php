<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;

/**
 * Interface for the eTrans translation request bundle class.
 */
interface TranslationRequestEtransInterface extends TranslationRequestRemoteInterface {

  const STATUS_LANGUAGE_FAILED = 'Failed';

  /**
   * Returns the remote ID.
   *
   * @return string|null
   *   The ID if set.
   */
  public function getRemoteId(): ?string;

  /**
   * Sets the remote ID.
   *
   * @param string $remote_id
   *   The remote ID.
   *
   * @return \Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   *   The current entity.
   */
  public function setRemoteId(string $remote_id): TranslationRequestEtransInterface;

  /**
   * Returns the access token for the request.
   *
   * @return string|null
   *   The access token.
   */
  public function getSavedAccessToken(): ?string;

  /**
   * Generates an access token for a given translation request.
   *
   * This is used to secure the communication with DGT.
   *
   * @return string
   *   The token.
   */
  public function generateAccessToken(): string;

}
