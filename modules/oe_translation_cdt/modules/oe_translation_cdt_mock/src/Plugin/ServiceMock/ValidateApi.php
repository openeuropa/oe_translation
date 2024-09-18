<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use GuzzleHttp\Psr7\Response;
use OpenEuropa\CdtClient\Endpoint\ValidateEndpoint;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts validate request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_validate_api",
 *   label = @Translation("CDT mocked validate responses for testing."),
 *   weight = -2,
 * )
 */
class ValidateApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrlPath(): string {
    return ValidateEndpoint::ENDPOINT_URL_PATH;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    $this->log('200: Successfully validating the translation request.', $request);
    return new Response(200, [], 'true');
  }

}
