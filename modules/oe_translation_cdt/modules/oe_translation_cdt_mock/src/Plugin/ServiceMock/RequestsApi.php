<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts "requests" request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_requests_api",
 *   label = @Translation("CDT mocked 'requests' responses for testing."),
 *   weight = -1,
 * )
 */
class RequestsApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.requests_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if (!$this->hasToken($request)) {
      return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
    }

    // Make the correlation ID equal to the translation request ID.
    // This way, we can identify it easily on the later stages.
    $request_json = json_decode($request->getBody()->getContents(), TRUE);
    $entity_id = $request_json['clientReference'];
    return new Response(200, [], $entity_id);
  }

}
