<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

/**
 * Tests the 'Translation request log' entity.
 */
class TranslationRequestLogEntityTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request_log');
  }

  /**
   * Tests Translation Request entities.
   */
  public function testTranslationRequestLogEntity(): void {
    // Create a translation request log entity.
    $translation_request_log_storage = $this->container->get('entity_type.manager')->getStorage('oe_translation_request_log');
    /** @var \Drupal\oe_translation\Entity\TranslationRequestLogInterface $translation_request_log */
    $translation_request_log = $translation_request_log_storage->create([
      'message' => '@status: The translation request message.',
      'variables' => [
        '@status' => 'Draft',
      ],
    ]);
    $translation_request_log->save();

    // Assert default type value.
    $this->assertEquals('info', $translation_request_log->getType());
    $translation_request_log->setType('error');
    $this->assertEquals('error', $translation_request_log->getType());
    $this->assertEquals('Draft: The translation request message.', $translation_request_log->getMessage());
  }

}
