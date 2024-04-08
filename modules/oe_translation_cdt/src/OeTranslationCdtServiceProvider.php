<?php

namespace Drupal\oe_translation_cdt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;
use OpenEuropa\CdtClient\ApiClient;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers a CDT Client service with custom config.
 */
class OeTranslationCdtServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->register('oe_translation_cdt.api_client', ApiClient::class)
      ->addArgument(new Reference('http_client'))
      ->addArgument(new Reference('psr17.server_request_factory'))
      ->addArgument(new Reference('psr17.stream_factory'))
      ->addArgument(new Reference('oe_translation_cdt.psr17.uri_factory'))
      ->addArgument([
        'mainApiEndpoint' => Settings::get('cdt.main_api_endpoint'),
        'tokenApiEndpoint' => Settings::get('cdt.token_api_endpoint'),
        'referenceDataApiEndpoint' => Settings::get('cdt.reference_data_api_endpoint'),
        'validateApiEndpoint' => Settings::get('cdt.validate_api_endpoint'),
        'requestsApiEndpoint' => Settings::get('cdt.requests_api_endpoint'),
        'identifierApiEndpoint' => Settings::get('cdt.identifier_api_endpoint'),
        'statusApiEndpoint' => Settings::get('cdt.status_api_endpoint'),
        'username' => Settings::get('cdt.username'),
        'password' => Settings::get('cdt.password'),
        'client' => Settings::get('cdt.client'),
        'api_key' => Settings::get('cdt.api_key'),
      ]);
  }

}
