<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

use Drupal\Component\Datetime\Time;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_cdt\Exception\CdtConnectionException;
use GuzzleHttp\Client;
use OpenEuropa\CdtClient\ApiClient;
use OpenEuropa\CdtClient\Model\Response\Token;

/**
 * Authenticates connection with CDT client.
 */
class ApiAuthenticator implements ApiAuthenticatorInterface {

  /**
   * The authentication status.
   */
  protected bool $authenticated = FALSE;

  /**
   * Constructs an ApiAuthenticator object.
   *
   * @param \OpenEuropa\CdtClient\ApiClient $apiClient
   *   The CDT API client.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Component\Datetime\Time $time
   *   The time service.
   */
  public function __construct(
    protected readonly ApiClient $apiClient,
    protected readonly Client $httpClient,
    protected readonly StateInterface $state,
    protected readonly Time $time
  ) {}

  /**
   * {@inheritdoc}
   */
  public function authenticate(): void {
    if ($this->authenticated) {
      return;
    }

    $expiry = $this->state->get('cdt.token_expiry_date');
    $is_expired = $expiry && $expiry < $this->time->getCurrentTime();

    /** @var \OpenEuropa\CdtClient\Model\Response\Token|false $state_token */
    $state_token = unserialize((string) $this->state->get('cdt.token'), [
      'allowed_classes' => [Token::class],
    ]);

    if (!$state_token || $is_expired) {
      $this->requestNewToken();
      $this->authenticated = TRUE;
      return;
    }

    $this->apiClient->setToken($state_token);
    if (!$this->apiClient->checkConnection()) {
      // If the stored token is invalid, try to retrieve a new one.
      $this->requestNewToken();
    }

    $this->authenticated = TRUE;
  }

  /**
   * Requests a new token and sets the state.
   */
  protected function requestNewToken(): void {
    $this->resetAuthentication();
    $token = $this->apiClient->requestToken();
    $this->state->set('cdt.token', serialize($token));
    $this->state->set('cdt.token_expiry_date', $this->time->getCurrentTime() + $token->getExpiresIn());
    $this->apiClient->setToken($token);
    if (!$this->apiClient->checkConnection()) {
      throw new CdtConnectionException('The connection to the CDT API could not be established.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resetAuthentication(): void {
    $this->state->delete('cdt.token');
    $this->state->delete('cdt.token_expiry_date');
    $this->authenticated = FALSE;
  }

}
