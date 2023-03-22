<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the language manager service.
 */
class OeTranslationEpoetryMockServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('oe_translation_epoetry.request_factory')) {
      $definition = $container->getDefinition('oe_translation_epoetry.request_factory');
      $definition->setClass(MockRequestFactory::class);
    }

    if ($container->hasDefinition('oe_translation_epoetry.notification_ticket_validation')) {
      $definition = $container->getDefinition('oe_translation_epoetry.notification_ticket_validation');
      $definition->setClass(MockNotificationTicketValidation::class);
    }
  }

}
