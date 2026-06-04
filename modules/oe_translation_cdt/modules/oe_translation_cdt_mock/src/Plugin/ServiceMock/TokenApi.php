<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use GuzzleHttp\Psr7\Response;
use OpenEuropa\CdtClient\Endpoint\TokenEndpoint;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts token request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_token_api",
 *   label = @Translation("CDT mocked token responses for testing."),
 *   weight = -2,
 * )
 */
class TokenApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrlPath(): string {
    return TokenEndpoint::ENDPOINT_URL_PATH;
  }

  /**
   * {@inheritdoc}
   */
  protected function needsToken(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    $this->log('200: Returning the mocked authorization token.', $request);
    return new Response(200, [], $this->getResponseFromFile('token_response_200.json'));
  }

}
