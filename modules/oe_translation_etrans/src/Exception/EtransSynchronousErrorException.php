<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\Exception;

/**
 * An exception to be thrown when we receive an error code from etrans.
 */
class EtransSynchronousErrorException extends \Exception {

  /**
   * The etrans error code.
   *
   * @var string
   */
  protected $etransErrorCode = '';

  /**
   * Returns the etrans error code.
   *
   * @return string
   *   The error code.
   */
  public function getEtransErrorCode() {
    return $this->etransErrorCode;
  }

  /**
   * Sets the error code.
   *
   * @param string $code
   *   The error code.
   */
  public function setEtransErrorCode(string $code): void {
    $this->etransErrorCode = $code;
  }

}
