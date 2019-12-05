<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Overrides the Poetry service for the test.
 */
class OeTranslationPoetryTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    try {
      $definition = $container->getDefinition('oe_translation_poetry.client_factory');
      if ($definition) {
        $definition->setClass(PoetryFactoryTest::class);
      }
    }
    catch (\Exception $exception) {
      // Do nothing.
    }

  }

}
