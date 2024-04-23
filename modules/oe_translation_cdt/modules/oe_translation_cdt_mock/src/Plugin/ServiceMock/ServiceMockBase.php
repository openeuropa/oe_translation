<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt_mock\Plugin\ServiceMock;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Intercepts token request made to CDT.
 *
 * @ServiceMock(
 *   id = "oe_translation_cdt_token",
 *   label = @Translation("CDT mocked token responses for testing."),
 *   weight = -1,
 * )
 */
abstract class ServiceMockBase extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a Token object.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ModuleExtensionList $moduleExtensionList,
    protected EntityTypeManagerInterface $entityTypeManager
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Get the endpoint URL.
   *
   * @return string
   *   The endpoint URL to match against the current request.
   *   All parameters like ":id" will be considered as wildcards.
   */
  abstract protected function getEndpointUrl(): string;

  /**
   * Check if bearer token is present.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return bool
   *   TRUE if the request has a valid token, FALSE otherwise.
   */
  protected function hasToken(RequestInterface $request): bool {
    $header = $request->getHeader('Authorization');
    return $header == 'Bearer TEST_TOKEN';
  }

  /**
   * Get the request parameters from the request path.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return array
   *   The request parameters in the associative array.
   */
  protected function getRequestParameters(RequestInterface $request): array {
    $url_parts = explode('/', $request->getUri()->getPath());
    $endpoint_parts = explode('/', $this->getEndpointUrl());
    $parameters = [];
    foreach ($endpoint_parts as $key => $part) {
      if (str_starts_with($part, ':')) {
        $parameters[$part] = $url_parts[$key] ?? NULL;
      }
    }
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    $endpoint_pattern = preg_replace('/:\w+/', '[^/]*', $this->getEndpointUrl());
    return preg_match("|^$endpoint_pattern$|", (string) $request->getUri()) === 1;
  }

  /**
   * Gets the response from a file.
   *
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The response.
   */
  public function getResponseFromFile(string $filename): string {
    return (string) file_get_contents($this->moduleExtensionList->getPath('oe_translation_cdt_mock') . '/responses/json/' . $filename);
  }

}
