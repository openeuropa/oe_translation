<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Compiler;

use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Registers the cdt.client_configuration container parameter.
 */
class CdtParametersCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
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
