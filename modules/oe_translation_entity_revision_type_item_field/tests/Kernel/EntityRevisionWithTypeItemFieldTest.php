<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_entity_revision_type_item_field\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the Poetry Request ID field type, widget and formatter.
 */
class EntityRevisionWithTypeItemFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'oe_translation_entity_revision_type_item_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'entity_revision_type_item',
      'entity_type' => 'node',
      'type' => 'oe_translation_entity_revision_type_item',
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'entity_revision_type_item',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the field type and formatter.
   */
  public function testEntityRevisionWithTypeItemField() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $entity_revision_with_type_item = [
      'entity_id' => 'Entity ID',
      'entity_revision' => 'Entity revision',
      'entity_type' => 'Entity type',
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
      'entity_revision_type_item' => $entity_revision_with_type_item,
    ]);

    $node->save();
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertEquals($entity_revision_with_type_item, $node->get('entity_revision_type_item')->first()->getValue());

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $build = $builder->viewField($node->get('entity_revision_type_item'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertContains('Entity ID', (string) $output);
    $this->assertContains('Entity revision', (string) $output);
    $this->assertContains('Entity type', (string) $output);
  }

}
