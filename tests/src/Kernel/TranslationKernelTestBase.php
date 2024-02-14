<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;

/**
 * Base class for Kernel tests that test translation functionality.
 */
class TranslationKernelTestBase extends KernelTestBase {

  use TranslationsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'system',
    'node',
    'field',
    'file',
    'link',
    'text',
    'options',
    'user',
    'language',
    'block',
    'oe_multilingual',
    'oe_multilingual_demo',
    'content_translation',
    'oe_translation',
    'views',
    'datetime',
    'oe_translation_test',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'menu_ui',
    'path',
    'pathauto',
    'path_alias',
    'token',
    'filter',
    'ctools',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\oe_translation\TranslationSourceManagerInterface
   */
  protected $translationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->translationManager = $this->container->get('oe_translation.translation_source_manager');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);

    $this->installConfig([
      'system',
      'node',
      'field',
      'link',
      'language',
      'oe_multilingual',
      'oe_multilingual_demo',
      'oe_translation',
      'views',
      'user',
      'oe_translation_test',
      'menu_link_content',
      'menu_ui',
      'pathauto',
      'filter',
    ]);

    // Create a node type and set it translatable.
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();
    node_add_body_field($type);
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('router.builder')->rebuild();
  }

}
