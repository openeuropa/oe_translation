<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\oe_translation_cdt\Compiler\CdtParametersCompilerPass;

/**
 * Runs the custom compiler pass.
 */
class OeTranslationCdtServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $container->addCompilerPass(new CdtParametersCompilerPass());
  }

}
