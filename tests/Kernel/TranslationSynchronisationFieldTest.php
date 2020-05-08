<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests the Poetry Request ID field type, widget and formatter.
 */
class TranslationSynchronisationFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['serialization'];

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->serializer = \Drupal::service('serializer');
    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'translation_sync',
      'entity_type' => 'node',
      'type' => 'oe_translation_translation_sync',
    ])->save();

    FieldConfig::create([
      'field_name' => 'translation_sync',
      'entity_type' => 'node',
      'bundle' => 'page',
    ])->save();
  }

  /**
   * Tests the field type and formatter.
   */
  public function testTranslationSynchronisationItemField() {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    $translation_sync_values = [
      'type' => 'automatic',
      'configuration' => [
        'language' => 'Bulgarian',
        'date' => '2020-05-08',
      ],
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
      'translation_sync' => $translation_sync_values,
    ]);

    $node->save();
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());

    $this->assertEquals($translation_sync_values, $node->get('translation_sync')->first()->getValue());

    $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
    $build = $builder->viewField($node->get('translation_sync'));
    $output = $this->container->get('renderer')->renderRoot($build);
    $this->assertContains('automatic', (string) $output);

    // Test serialization.
    $serialized = $this->serializer->serialize($node, 'json');
    $deserialized = $this->serializer->deserialize($serialized, Node::class, 'json');
    $expected_values = [
      'language' => 'Bulgarian',
      'date' => '2020-05-08',
    ];
    $this->assertSame($expected_values, $deserialized->translation_sync->configuration);
  }

}
