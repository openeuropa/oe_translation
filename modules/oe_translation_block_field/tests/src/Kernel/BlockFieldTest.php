<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_block_field\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;

/**
 * Tests for the block title translation.
 */
class BlockFieldTest extends ContentEntityTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'block',
    'block_field',
    'oe_translation_block_field',
    'oe_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * The test entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entityTest;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['block_field']);

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_block_field',
      'entity_type' => $this->entityTypeId,
      'type' => 'block_field',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'translatable' => TRUE,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'entity_type' => $this->entityTypeId,
      'field_storage' => $field_storage,
      'bundle' => $this->entityTypeId,
      'required' => FALSE,
    ])->setTranslatable(TRUE)->save();

    $values = [
      'title' => 'My entity',
      'langcode' => 'en',
      'user_id' => 1,
    ];
    $this->entityTest = EntityTestMul::create($values);
  }

  /**
   * Tests that the block field is translatable.
   */
  public function testBlockFieldTranslation(): void {
    // Fill the fields with test data.
    $this->entityTest->get('field_block_field')->plugin_id = 'system_powered_by_block';
    $this->entityTest->get('field_block_field')->settings = [
      'label' => 'Hello',
      'label_display' => TRUE,
      'content' => 'World',
    ];
    $this->entityTest->save();

    /** @var \Drupal\oe_translation\TranslationSourceManagerInterface $manager */
    $manager = \Drupal::service('oe_translation.translation_source_manager');
    $data = $manager->extractData($this->entityTest);
    $this->assertEquals($data['field_block_field'][0]['settings__label']['#text'], 'Hello');

    // Save the translated data onto the entity.
    $data['field_block_field'][0]['settings__label']['#translation']['#text'] = 'Hello DE';
    $manager->saveData($data, $this->entityTest, 'de');

    // Check that the translation was saved correctly on the entity.
    $this->entityTest = $this->container->get('entity_type.manager')->getStorage($this->entityTypeId)->load($this->entityTest->id());
    $translation = $this->entityTest->getTranslation('de');
    $field_block_field = $translation->get('field_block_field')->settings;
    $this->assertEquals($field_block_field['label'], 'Hello DE');
  }

}
