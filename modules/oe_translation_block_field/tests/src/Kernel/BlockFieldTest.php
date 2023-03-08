<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_block_field\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests for the block title translation.
 *
 * @group batch1
 */
class BlockFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_field',
    'oe_translation_block_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * The test node.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $testNode;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['block_field']);
    $this->installSchema('node', ['node_access']);

    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'name' => 'Test node type',
        'type' => 'test_node_type',
      ]);

    $node_type->save();

    $node_type = $this->container->get('entity_type.manager')
      ->getStorage('node_type')
      ->create([
        'name' => 'Test node type',
        'type' => 'test_node_type',
      ]);

    $node_type->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_block_field',
      'entity_type' => 'node',
      'type' => 'block_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'translatable' => TRUE,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_storage' => $field_storage,
      'bundle' => 'test_node_type',
      'required' => FALSE,
    ])->setTranslatable(TRUE)->save();

    $values = [
      'title' => 'My node',
      'langcode' => 'en',
      'type' => 'test_node_type',
    ];
    $this->testNode = Node::create($values);
  }

  /**
   * Tests that the block field is translatable.
   */
  public function testBlockFieldTranslation(): void {
    // Fill the fields with test data.
    $this->testNode->get('field_block_field')->plugin_id = 'system_powered_by_block';
    $this->testNode->get('field_block_field')->settings = [
      'label' => 'Hello',
      'label_display' => TRUE,
      'content' => 'World',
    ];
    $this->testNode->save();

    /** @var \Drupal\oe_translation\TranslationSourceManagerInterface $manager */
    $manager = \Drupal::service('oe_translation.translation_source_manager');
    $data = $manager->extractData($this->testNode);
    $this->assertEquals($data['field_block_field'][0]['settings__label']['#text'], 'Hello');

    // Save the translated data onto the entity.
    $data['field_block_field'][0]['settings__label']['#translation']['#text'] = 'Hello DE';
    $manager->saveData($data, $this->testNode, 'de');

    // Check that the translation was saved correctly on the entity.
    $this->testNode = $this->container->get('entity_type.manager')->getStorage('node')->load($this->testNode->id());
    $translation = $this->testNode->getTranslation('de');
    $field_block_field = $translation->get('field_block_field')->settings;
    $this->assertEquals($field_block_field['label'], 'Hello DE');
  }

}
