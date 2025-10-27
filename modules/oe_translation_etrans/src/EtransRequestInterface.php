<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

/**
 * Interface for the etrans request entities.
 */
interface EtransRequestInterface {

  /**
   * Sets the domain.
   *
   * @param string $domain
   *   The domain.
   */
  public function setDomain(string $domain): void;

  /**
   * Returns the JSON representation.
   *
   * @return string
   *   The JSON string.
   */
  public function toJson(): string;

  /**
   * Builds the caller information.
   *
   * @return array
   *   The caller information.
   */
  public function getCallerInformation(): array;

}
