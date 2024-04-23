<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts validate request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_validate_api",
 *   label = @Translation("CDT mocked validate responses for testing."),
 *   weight = -1,
 * )
 */
class ValidateApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.validate_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if (!$this->hasToken($request)) {
      return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
    }
    return new Response(200, [], 'true');
  }

}
