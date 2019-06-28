<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_translation\TranslationModerationHandler;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests that whenever a new translation is saved, no revision is created.
 *
 * This is needed whenever the site uses content moderation which forces a
 * new revision every time the node is saved.
 */
class TranslationsRevisionTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'field',
    'options',
    'text',
    'node',
    'user',
    'system',
    'filter',
    'workflows',
    'language',
    'oe_multilingual',
    'content_moderation',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->installConfig([
      'system',
      'node',
      'field',
      'user',
      'workflows',
      'language',
      'oe_multilingual',
      'content_moderation',
    ]);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');

    $this->installSchema('node', 'node_access');

    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $node_type->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()
      ->addEntityTypeAndBundle('node', 'test_node_type');

    $workflow->save();
  }

  /**
   * Tests that no revisions are created when saving a translation.
   *
   * This happens when a node type uses a content moderation workflow and we
   * prevent new revisions from being created when a new translation is saved.
   */
  public function testNoRevisionIncrement(): void {
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'test_node_type',
      'title' => 'Revisions',
    ]);

    $node->save();
    $this->assertCount(1, $storage->revisionIds($node));
    $node->save();
    $this->assertCount(2, $storage->revisionIds($node));

    // Add a translation and assert that translations create revisions as per
    // the default content moderation setup.
    $translation = $node->addTranslation('fr', ['title' => 'Revisions FR']);
    $translation->save();
    $this->assertCount(3, $storage->revisionIds($node));

    // Install our module and check that saving translations no longer creates
    // extra revisions.
    $this->enableModules(['tmgmt', 'oe_translation']);
    $this->installConfig(['tmgmt', 'oe_translation']);
    $this->assertInstanceOf(TranslationModerationHandler::class, $this->container->get('entity_type.manager')->getHandler('node', 'moderation'));
    $translation = $node->addTranslation('de', ['title' => 'Revisions DE']);
    $translation->save();
    $this->assertCount(3, $storage->revisionIds($node));
    $translation->set('title', 'Revisions DE 2');
    $translation->save();
    $this->assertCount(3, $storage->revisionIds($node));
    // Saving on the source node should create a new revision.
    $node->set('title', 'Revisions 2');
    $node->save();
    $this->assertCount(4, $storage->revisionIds($node));
  }

}
