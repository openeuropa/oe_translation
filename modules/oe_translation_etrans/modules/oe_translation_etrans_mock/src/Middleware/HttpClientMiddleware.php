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

          // Throw an error if globally configured.
          $error = $this->state->get('oe_translation_etrans_mock.error_response', []);
          if ($error) {
            $response = new Response(headers: [
              'Content-Type' => 'application/json',
            ], body: json_encode($error));

            return new FulfilledPromise($response);
          }

          // Throw an 502 if globally configured.
          $error = $this->state->get('oe_translation_etrans_mock.error_response_502', FALSE);
          if ($error) {
            $response = new Response(status: 502, headers: [
              'Content-Type' => 'application/json',
            ]);

            return new FulfilledPromise($response);
          }

          // Check to see the node being translated in case we need to throw
          // an error for a specific request.
          $contents = $request->getBody()->getContents();
          $request->getBody()->rewind();
          $decoded = json_decode($contents);
          $translation_requests = \Drupal::entityTypeManager()->getStorage('oe_translation_request')->loadByProperties(['etrans_access_token' => $decoded->callerInformation->externalReference]);
          $translation_request = reset($translation_requests);
          $document = \Drupal::service('oe_translation_content_formatter.html_formatter')->import(base64_decode($decoded->documentToTranslate->document->content), $translation_request);
          $document = reset($document);
          if (isset($document['title']) && str_contains($document['title'][0]['value']['#text'], 'errorCode:')) {
            $response = new Response(headers: [
              'Content-Type' => 'application/json',
            ], body: json_encode([
              'errorCode' => str_replace('errorCode:', '', $document['title'][0]['value']['#text']),
              'errorMessage' => 'Error message',
            ]));

            return new FulfilledPromise($response);
          }

          $request_id = $this->state->get('oe_translation_etrans_mock.default_request_id', '55555');
          $response = new Response(headers: [
            'Content-Type' => 'application/json',
          ], body: json_encode([
            'requestId' => $request_id,
          ]));
          return new FulfilledPromise($response);
        }

        if (str_contains($uri->getPath(), 'etranslation/api/status')) {

          // Return a specific heavy usage for testing.
          $heavy_usage = $this->state->get('oe_translation_etrans_mock.heavy_usage', []);
          if ($heavy_usage) {
            $response = new Response(headers: [
              'Content-Type' => 'application/json',
            ], body: json_encode($heavy_usage));
            return new FulfilledPromise($response);
          }

          // By default, return a normal status.
          $response = new Response(headers: [
            'Content-Type' => 'application/json',
          ], body: json_encode([
            'level' => '0',
            'message' => 'Normal service',
          ]));
          return new FulfilledPromise($response);
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

}
