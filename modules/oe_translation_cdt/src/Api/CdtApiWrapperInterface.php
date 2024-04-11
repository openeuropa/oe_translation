<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

use OpenEuropa\CdtClient\Contract\ApiClientInterface;

/**
 * The interface for CDT API client wrapper.
 */
interface CdtApiWrapperInterface {

  /**
   * Gets and authenticates the client.
   *
   * @return \OpenEuropa\CdtClient\Contract\ApiClientInterface
   *   The CDT API client.
   *
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
   * @throws \OpenEuropa\CdtClient\Exception\InvalidStatusCodeException
   */
  public function getClient(): ApiClientInterface;

  /**
   * Resets the authentication and forces a new token retrieval.
   */
  public function resetAuthentication(): void;

}
