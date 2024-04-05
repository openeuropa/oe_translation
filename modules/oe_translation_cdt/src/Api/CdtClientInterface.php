<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

use OpenEuropa\CdtClient\ApiClient;

/**
 * The interface for CDT API client class.
 */
interface CdtClientInterface {

  /**
   * Get the initialized CDT client.
   *
   * @return \OpenEuropa\CdtClient\ApiClient
   *   The CDT client.
   */
  public function client(): ApiClient;

}
