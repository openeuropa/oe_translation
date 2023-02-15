<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_corporate_workflow_epoetry;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Override services for the corporate workflow.
 */
class OeTranslationCorporateWorkflowEpoetryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('oe_translation_epoetry.new_version_request_handler')) {
      $definition = $container->getDefinition('oe_translation_epoetry.new_version_request_handler');
      $definition->setClass(CorporateWorkflowEpoetryOngoingNewVersionRequestHandler::class);
      $definition->addArgument(new Reference('content_moderation.moderation_information'));
    }
  }

}
