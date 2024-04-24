<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts identifier request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_identifier_api",
 *   label = @Translation("CDT mocked identifier responses for testing."),
 *   weight = -1,
 * )
 */
class IdentifierApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.identifier_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if (!$this->hasToken($request)) {
      return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
    }

    $parameters = $this->getRequestParameters($request);
    return new Response(200, [], sprintf('%s/%s', date('Y'), $parameters['correlationId']));
  }

}
