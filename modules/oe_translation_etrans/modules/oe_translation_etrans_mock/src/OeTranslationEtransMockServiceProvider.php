<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans_mock;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Override the etrans client class.
 */
class OeTranslationEtransMockServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('oe_translation_etrans.client')) {
      $definition = $container->getDefinition('oe_translation_etrans.client');
      $definition->setClass(EtransMockClient::class);
    }
  }

}
