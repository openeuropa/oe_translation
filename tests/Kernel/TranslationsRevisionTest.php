<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_translation\TranslationModerationHandler;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests that whenever a new translation is saved, no revision is created.
 *
 * This is needed whenever the site uses content moderation which forces a
 * new revision every time the node is saved.
 */
class TranslationsRevisionTest extends KernelTestBase {

  use ContentModerationTestTrait;
  use UserCreationTrait;

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
    'content_translation',
    'views',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

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
    $this->installEntitySchema('path_alias');

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

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

  /**
   * Tests that the moderation state is not translatable.
   *
   * When moving the moderation state of a language, ensure that the moderation
   * state of all other languages follow suit.
   */
  public function testNonTranslatableModerationState(): void {
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach (['oe_translation_disabled', 'oe_translation_enabled'] as $status) {
      // Before enabling oe_translation, the moderation state will differ
      // per language. After enabling, all translations should have the same
      // moderation state regardless which translation was updated.
      if ($status === 'oe_translation_enabled') {
        $this->enableModules(['tmgmt', 'oe_translation']);
        $this->installConfig(['tmgmt', 'oe_translation']);
      }

      /** @var \Drupal\node\NodeInterface $node */
      $node = $storage->create([
        'type' => 'test_node_type',
        'title' => 'Test node EN',
      ]);
      $node->save();
      $this->assertEquals('draft', $node->moderation_state->value);

      $translation = $node->addTranslation('fr', ['title' => 'Test node FR', 'moderation_state' => 'draft']);
      $translation->save();
      $this->assertEquals('draft', $translation->moderation_state->value);

      $translation->set('moderation_state', 'published');
      $translation->save();
      $this->assertEquals('published', $translation->moderation_state->value);
      $node = $storage->load($node->id());
      $this->assertEquals($status === 'oe_translation_enabled' ? 'published' : 'draft', $node->moderation_state->value);
    }
  }

  /**
   * Tests that there is no access to update directly node translations.
   */
  public function testNoTranslationUpdateAccess(): void {
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'test_node_type',
      'title' => 'Test node',
      'moderation_state' => 'draft',
    ]);

    $node->save();

    $this->setUpCurrentUser([], [
      'access content',
      'edit any test_node_type content',
      'use editorial transition create_new_draft',
    ]);

    $translation = $node->addTranslation('fr', ['title' => 'Test node FR']);
    $translation->save();
    // Before installing oe_translation, both can be updated.
    $this->assertTrue($node->access('update'));
    $this->assertTrue($translation->access('update'));

    $this->enableModules(['tmgmt', 'oe_translation']);
    $this->installConfig(['tmgmt', 'oe_translation']);

    // Now only the source language can be updated.
    $this->assertTrue($node->access('update'));
    $this->assertFalse($translation->access('update'));
  }

}
