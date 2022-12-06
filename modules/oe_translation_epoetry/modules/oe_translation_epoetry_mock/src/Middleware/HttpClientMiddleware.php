<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock\Middleware;

use Drupal\Core\State\StateInterface;
use Psr\Http\Message\RequestInterface;

/**
 * EPoetry SOAP request HTTP client middleware.
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

        if (str_contains($uri->getPath(), 'epoetry-mock/server')) {
          // Log the requests done to the epoetry mock.
          $requests = $this->state->get('oe_translation_epoetry_mock.mock_requests', []);
          $requests[] = trim(str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $request->getBody()->getContents()));
          $request->getBody()->rewind();
          $this->state->set('oe_translation_epoetry_mock.mock_requests', $requests);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

}
