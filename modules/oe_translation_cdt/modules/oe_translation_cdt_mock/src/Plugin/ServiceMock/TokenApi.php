<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
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
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.token_api_endpoint');
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
