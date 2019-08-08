<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\tmgmt_content\DefaultFieldProcessor;

/**
 * Tests that the link fields use the default field processor for TMGMT.
 *
 * This is needed to ensure that we can translate also the URI column of the
 * link field type.
 */
class LinkFieldProcessorTest extends TranslationKernelTestBase {

  /**
   * Tests that the default field processor on the link field type is used.
   */
  public function testLinkFieldProcessor(): void {
    $definition = $this->container->get('plugin.manager.field.field_type')->getDefinition('link');
    $this->assertEquals($definition['tmgmt_field_processor'], DefaultFieldProcessor::class);
  }

}
