<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The base class for all CDT API mocks.
 */
abstract class ServiceMockBase extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a ServiceMockBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('extension.list.module'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Gets the endpoint URL.
   *
   * @return string
   *   The endpoint URL to match against the current request.
   *   All parameters like ":id" will be considered as wildcards.
   */
  abstract protected function getEndpointUrl(): string;

  /**
   * Gets the response from the endpoint.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  abstract protected function getEndpointResponse(RequestInterface $request): ResponseInterface;

  /**
   * Determines if the endpoint needs authorization.
   *
   * Override it to FALSE for public endpoints.
   *
   * @return bool
   *   TRUE if the authorization token is required, FALSE otherwise.
   */
  protected function needsToken(): bool {
    return TRUE;
  }

  /**
   * Gets the request parameters from the request path.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return array
   *   The request parameters in the associative array.
   */
  protected function getRequestParameters(RequestInterface $request): array {
    $url_parts = explode('/', $request->getUri()->getPath());
    $endpoint_parts = explode('/', (string) parse_url($this->getEndpointUrl(), PHP_URL_PATH));
    $parameters = [];
    foreach ($endpoint_parts as $key => $part) {
      if (str_starts_with($part, ':')) {
        $parameters[ltrim($part, ':')] = $url_parts[$key] ?? NULL;
      }
    }
    return $parameters;
  }

  /**
   * Logs a debug message.
   *
   * @param string $message
   *   The message to log.
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   */
  protected function log(string $message, RequestInterface $request): void {
    $request->getBody()->rewind();
    $this->loggerFactory->get('oe_translation_cdt_mock')->debug(sprintf(
      "%s <br><strong>Request URL:</strong> %s<br><strong>Request data:</strong> <pre>%s</pre>",
      $message,
      $request->getUri(),
      $request->getBody()->getContents() ?: 'No data'
    ));
  }

  /**
   * Gets the response from a JSON file.
   *
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The response text.
   */
  public function getResponseFromFile(string $filename): string {
    return (string) file_get_contents($this->moduleExtensionList->getPath('oe_translation_cdt_mock') . '/responses/json/' . $filename);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    // Replace all URL parameters with regex wildcards.
    $endpoint_pattern = preg_replace('/:\w+/', '[^/]*', $this->getEndpointUrl());
    return preg_match("|^$endpoint_pattern$|", (string) $request->getUri()) === 1;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    if ($this->needsToken()) {
      $header = $request->getHeader('Authorization');
      if (($header[0] ?? '') !== 'Bearer TEST_TOKEN') {
        $this->log('401: The authorization token is incorrect or does not exist.', $request);
        return new Response(401, [], $this->getResponseFromFile('general_response_401.json'));
      }
    }
    return $this->getEndpointResponse($request);
  }

}
