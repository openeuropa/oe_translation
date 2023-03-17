<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\oe_translation\TranslationSourceFieldProcessor\DefaultFieldProcessor;

/**
 * Test that the link fields use the default translation source field processor.
 *
 * This is needed to ensure that we can translate also the URI column of the
 * link field type.
 *
 * @group batch1
 */
class LinkFieldProcessorTest extends TranslationKernelTestBase {

  /**
   * Tests that the default field processor on the link field type is used.
   */
  public function testLinkFieldProcessor(): void {
    $definition = $this->container->get('plugin.manager.field.field_type')->getDefinition('link');
    $this->assertEquals($definition['oe_translation_source_field_processor'], DefaultFieldProcessor::class);
  }

}
