<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

/**
 * Tests the translator providers service.
 */
class TranslatorProvidersTest extends TranslationKernelTestBase {

  /**
   * Tests that the node entity type has its definition updated.
   */
  public function testTranslatorProviders(): void {
    $translator_providers_service = \Drupal::service('oe_translation.translator_providers');
    $entity_type = \Drupal::entityTypeManager()->getDefinition('node');
    $this->assertTrue($translator_providers_service->hasLocal($entity_type));
    $this->assertTrue($translator_providers_service->hasRemote($entity_type));
    $remote = ['epoetry'];
    $this->assertEquals($remote, $translator_providers_service->getRemotePlugins($entity_type));
  }

}
