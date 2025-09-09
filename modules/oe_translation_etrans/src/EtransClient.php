<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Client for sending requests to DGT for etranslations.
 */
class EtransClient {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * Constructs a EtransClient.
   *
   * @param \GuzzleHttp\Client $client
   *   The HTTP client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Sends a translation request to the API.
   *
   * @param \Drupal\oe_translation_etrans\EtransRequest $request
   *   The request data object.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function sendRequest(EtransRequest $request) {
    $url = Settings::get('etrans.service_url');
    if (!$url) {
      throw new \Exception('Missing Etrans service URL');
    }
    $username = Settings::get('etrans.application_name');
    if (!$username) {
      throw new \Exception('Missing Etrans service username');
    }
    $password = Settings::get('etrans.password');
    if (!$password) {
      throw new \Exception('Missing Etrans service password');
    }

    $json = $request->toJson();

    return $this->client->post($url, [
      'body' => $json,
      'auth' => [$username, $password],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
    ]);
  }

}
