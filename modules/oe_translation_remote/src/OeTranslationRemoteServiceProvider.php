<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider for oe_translation_remote.
 */
class OeTranslationRemoteServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('oe_translation.content_translation_preview_manager')) {
      $definition = $container->getDefinition('oe_translation.content_translation_preview_manager');
      $definition->setClass(RemoteTranslationPreviewManager::class);
      $definition->addArgument(new Reference('plugin.manager.oe_translation_remote.remote_translation_provider_manager'));
    }
  }

}
