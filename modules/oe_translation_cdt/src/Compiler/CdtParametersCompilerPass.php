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
    // The container parameters can't be added as a single array.
    // Array values don't support the "%" character, that may
    // appear in the password or in the URL.
    $container->setParameter('cdt.base_api_url', Settings::get('cdt.base_api_url'));
    $container->setParameter('cdt.username', Settings::get('cdt.username'));
    $container->setParameter('cdt.password', Settings::get('cdt.password'));
    $container->setParameter('cdt.client', Settings::get('cdt.client'));
  }

}
