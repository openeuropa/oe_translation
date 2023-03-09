<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests the EntityRevisionWithType field type, widget and formatter.
 *
 * @group batch1
 */
class EntityRevisionWithTypeFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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

    // Create a node to reference.
    $node_storage->create([
      'type' => 'page',
      'title' => 'Referenced node page',
    ]);

    $value = [
      'entity_id' => '1',
      'entity_revision_id' => '1',
    ];

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
      'entity_revision_type_item' => $value,
    ]);
    $violations = $node->validate();
    $this->assertCount(2, $violations);
    $violation = $violations->get(0);
    $this->assertEqual('The entity type is missing.', $violation->getMessage());

    $value['entity_type'] = 'non_existing_entity_type';
    $node->set('entity_revision_type_item', $value);
    $violations = $node->validate();
    $this->assertCount(1, $violations);
    $violation = $violations->get(0);
    $this->assertEqual('The entity type is invalid.', $violation->getMessage());

    $value['entity_type'] = 'node';
    $node->set('entity_revision_type_item', $value);
    $violations = $node->validate();
    $this->assertCount(0, $violations);
    $node->save();

    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertEquals($value, $node->get('entity_revision_type_item')->first()->getValue());

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $build = $builder->viewField($node->get('entity_revision_type_item'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertStringContainsString('Test page', (string) $output);
    $this->assertStringNotContainsString('node-1-1', (string) $output);

    $value['entity_revision_id'] = 2;
    $node->set('entity_revision_type_item', $value);
    $build = $builder->viewField($node->get('entity_revision_type_item'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertStringNotContainsString('Test page', (string) $output);
    $this->assertStringContainsString('node-1-2', (string) $output);
  }

}
