<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Api;

use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Client;
use OpenEuropa\CdtClient\ApiClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Manages connection with CDT client.
 */
class CdtClient implements CdtClientInterface {

  /**
   * The CDT client.
   *
   * @var \OpenEuropa\CdtClient\ApiClient
   */
  protected ApiClient $client;

  /**
   * Constructs a CdtClient object.
   *
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Psr\Http\Message\RequestFactoryInterface $requestFactory
   *   The request factory.
   * @param \Psr\Http\Message\StreamFactoryInterface $streamFactory
   *   The stream factory.
   * @param \Psr\Http\Message\UriFactoryInterface $uriFactory
   *   The URI factory.
   */
  public function __construct(
    protected readonly Client $httpClient,
    protected readonly StateInterface $state,
    protected readonly RequestFactoryInterface $requestFactory,
    protected readonly StreamFactoryInterface $streamFactory,
    protected readonly UriFactoryInterface $uriFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function client(): ApiClient {
    if (!isset($this->client)) {
      $this->client = new ApiClient(
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
        $this->uriFactory,
        [
          'mainApiEndpoint' => Settings::get('cdt.main_api_endpoint'),
          'tokenApiEndpoint' => Settings::get('cdt.token_api_endpoint'),
          'referenceDataApiEndpoint' => Settings::get('cdt.reference_data_api_endpoint'),
          'validateApiEndpoint' => Settings::get('cdt.validate_api_endpoint'),
          'requestsApiEndpoint' => Settings::get('cdt.requests_api_endpoint'),
          'identifierApiEndpoint' => Settings::get('cdt.identifier_api_endpoint'),
          'statusApiEndpoint' => Settings::get('cdt.status_api_endpoint'),
          'username' => Settings::get('cdt.username'),
          'password' => Settings::get('cdt.password'),
          'client' => Settings::get('cdt.client'),
          'api_key' => Settings::get('cdt.api_key'),
        ]
      );

      // @todo Store token in State.
      // @todo Check connection.
      // @todo On error, try to obtain a new token.
      // @todo On error, set the state to "require verification".
      $token = $this->client->requestToken();
      $this->client->setToken($token);
    }
    return $this->client;
  }

}
