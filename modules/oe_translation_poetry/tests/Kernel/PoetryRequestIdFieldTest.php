<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Poetry Request ID field type, widget and formatter.
 */
class PoetryRequestIdFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page'
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'poetry_request_id',
      'entity_type' => 'node',
      'type' => 'poetry_request_id',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'poetry_request_id',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the field type and formatter.
   */
  public function testPoetryRequestIdField() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $poetry_id = [
      'code' => 'WEB',
      'year' => '2019',
      'number' => 122,
      'version' => 1,
      'part' => 1,
      'product' => 'TRA'
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
      'poetry_request_id' => $poetry_id,
    ]);

    $node->save();
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());

    $this->assertEqual($poetry_id, $node->get('poetry_request_id')->first()->getValue());

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $build = $builder->viewField($node->get('poetry_request_id'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertContains('WEB/2019/122/1/1/TRA', (string) $output);
  }

}
