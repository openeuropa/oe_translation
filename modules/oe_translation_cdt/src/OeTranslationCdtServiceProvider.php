<?php

namespace Drupal\oe_translation_cdt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Site\Settings;

/**
 * Updates the container with CDT client parameters.
 */
class OeTranslationCdtServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->setParameter('cdt.client_configuration', [
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
