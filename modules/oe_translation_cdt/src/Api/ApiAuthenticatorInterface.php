<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

/**
 * The interface for CDT API client class.
 */
interface ApiAuthenticatorInterface {

  /**
   * Authenticates the connection with the token.
   *
   * The token is stored in the state system, to avoid excessive API requests.
   * The connection is checked and the token is refreshed if necessary.
   *
   * @throws \Drupal\oe_translation_cdt\Exception\CdtConnectionException
   * @throws \OpenEuropa\CdtClient\Exception\InvalidStatusCodeException
   */
  public function authenticate(): void;

  /**
   * Resets the authentication state.
   */
  public function resetAuthentication(): void;

}
