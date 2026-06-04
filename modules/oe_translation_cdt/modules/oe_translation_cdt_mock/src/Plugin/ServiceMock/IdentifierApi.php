<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use GuzzleHttp\Psr7\Response;
use OpenEuropa\CdtClient\Endpoint\IdentifierEndpoint;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts identifier request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_identifier_api",
 *   label = @Translation("CDT mocked identifier responses for testing."),
 *   weight = -2,
 * )
 */
class IdentifierApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrlPath(): string {
    return IdentifierEndpoint::ENDPOINT_URL_PATH;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    $parameters = $this->getRequestParameters($request);
    $this->log('200: Returning the mocked permanent identifier.', $request);
    return new Response(200, [], sprintf('%s/%s', date('Y'), $parameters['correlationId']));
  }

}
