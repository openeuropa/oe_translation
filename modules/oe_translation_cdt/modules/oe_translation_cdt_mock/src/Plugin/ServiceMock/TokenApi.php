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
 *   weight = -1,
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
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    return new Response(200, [], $this->getResponseFromFile('token_response_200.json'));
  }

}
