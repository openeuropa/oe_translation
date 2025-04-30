<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans_mock\Middleware;

use Drupal\Core\State\StateInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Mocking middleware for the etrans requests.
 */
class HttpClientMiddleware {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a new HttpClientMiddleware.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $uri = $request->getUri();

        $config = \Drupal::config('oe_translation_etrans_mock.settings');
        if ((bool) $config->get('disable_middleware')) {
          // If the mock is configured to disable the middleware, just bypass
          // it.
          return $handler($request, $options);
        }

        if (str_contains($uri->getPath(), 'etranslation/api/askTranslate')) {
          // Log the requests done to the etrans mock.
          $requests = $this->state->get('oe_translation_etrans_mock.mock_requests', []);
          $requests[] = $request->getBody()->getContents();
          $request->getBody()->rewind();
          $this->state->set('oe_translation_etrans_mock.mock_requests', $requests);

          $response = new Response(headers: [
            'Content-Type' => 'application/json',
          ], body: json_encode([
            'requestId' => '55555',
          ]));
          return new FulfilledPromise($response);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

}
