<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts reference data request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_reference_data_api",
 *   label = @Translation("CDT mocked reference data responses for testing."),
 *   weight = -1,
 * )
 */
class ReferenceDataApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.reference_data_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    $this->log('200: Returning the mocked reference data.', $request);
    return new Response(200, [], $this->getResponseFromFile('reference_data_response_200.json'));
  }

}
