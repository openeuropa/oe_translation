<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Site\Settings;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts checkConnection request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_main_api",
 *   label = @Translation("CDT mocked checkConnection responses for testing."),
 *   weight = -2,
 * )
 */
class MainApi extends ServiceMockBase {

  /**
   * {@inheritdoc}
   */
  protected function getEndpointUrl(): string {
    return Settings::get('cdt.main_api_endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getEndpointResponse(RequestInterface $request): ResponseInterface {
    $this->log('200: Confirming that the connection works.', $request);
    return new Response(200, [], 'true');
  }

}
