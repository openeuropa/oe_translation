<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Kernel;

/**
 * Tests the translator providers service.
 *
 * @group batch1
 */
class TranslatorProvidersTest extends TranslationKernelTestBase {

  /**
   * Tests the translator providers configuration.
   */
  public function testTranslatorProviders(): void {
    /** @var \Drupal\oe_translation\TranslatorProvidersInterface $translator_providers_service */
    $translator_providers_service = \Drupal::service('oe_translation.translator_providers');
    // Asserts that the node entity type has its definition updated with the
    // oe_translation translators configuration.
    $entity_type = \Drupal::entityTypeManager()->getDefinition('node');
    $this->assertTrue($translator_providers_service->hasLocal($entity_type));
    $this->assertTrue($translator_providers_service->hasRemote($entity_type));
    $remote = ['epoetry'];
    $this->assertEquals($remote, $translator_providers_service->getRemotePlugins($entity_type));
    $this->assertEquals(['node'], array_keys($translator_providers_service->getDefinitions()));
    $this->assertTrue($translator_providers_service->hasTranslators($entity_type));

    // Asserts that the user entity type doesn't contain the oe_translation
    // translators configuration.
    $entity_type = \Drupal::entityTypeManager()->getDefinition('user');
    $this->assertFalse($translator_providers_service->hasLocal($entity_type));
    $this->assertFalse($translator_providers_service->hasRemote($entity_type));
    $remote = [];
    $this->assertEquals($remote, $translator_providers_service->getRemotePlugins($entity_type));
  }

}
