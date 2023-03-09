<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry_legacy\Kernel;

use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Legacy Poetry reference entity.
 *
 * @group batch2
 */
class LegacyPoetryReferenceEntityTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry_legacy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('poetry_legacy_reference');

    // Create a node to reference.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Page node',
    ]);
    $node->save();
  }

  /**
   * Tests Legacy Poetry reference entity.
   */
  public function testLegacyPoetryReferenceEntity(): void {
    // Create an empty Legacy Poetry reference entity.
    $poetry_legacy_reference_storage = $this->container->get('entity_type.manager')->getStorage('poetry_legacy_reference');
    /** @var \Drupal\oe_translation_poetry_legacy\Entity\LegacyPoetryReference $legacy_poetry_reference */
    $legacy_poetry_reference = $poetry_legacy_reference_storage->create([]);
    $legacy_poetry_reference->save();
    $this->assertNull($legacy_poetry_reference->getNode());
    $this->assertEmpty($legacy_poetry_reference->getPoetryId());

    // Set values to the created entity.
    $node = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $legacy_poetry_reference->setNode($node);
    $legacy_poetry_reference->setPoetryId('Poetry request ID');
    $legacy_poetry_reference->save();
    // Assert values are correctly saved.
    $this->assertEquals($node, $legacy_poetry_reference->getNode());
    $this->assertEquals('Poetry request ID', $legacy_poetry_reference->getPoetryId());
  }

}
