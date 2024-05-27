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
      'apiBaseUrl' => Settings::get('cdt.base_api_url'),
      'username' => Settings::get('cdt.username'),
      'password' => Settings::get('cdt.password'),
      'client' => Settings::get('cdt.client'),
    ]);
  }

}
