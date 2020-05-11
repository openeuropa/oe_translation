<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\oe_translation\Plugin\Field\FieldFormatter\TranslationSynchronisationFormatter;

/**
 * Tests the Poetry Request ID field type, widget and formatter.
 */
class TranslationSynchronisationFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('node', ['node_access']);

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
  public function testTranslationSynchronisationItemField(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    $date = new \DateTime('2020-05-08');
    $tests = [
      'manual' => [
        'type' => 'manual',
        'configuration' => [],
      ],
      'manual_languages' => [
        'type' => 'automatic',
        'configuration' => [
          'languages' => [
            'bg',
            'fr',
          ],
          'date' => NULL,
        ],
      ],
      'manual_languages_date' => [
        'type' => 'automatic',
        'configuration' => [
          'languages' => [
            'de',
            'es',
          ],
          'date' => $date->getTimestamp(),
        ],
      ],
    ];

    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'Test page',
    ]);
    $node->save();

    foreach ($tests as $values) {
      $node->set('translation_sync', $values);
      $node->save();
      $node_storage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($node->id());
      $this->assertEquals($values, $node->get('translation_sync')->first()->getValue());
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
      $build = $builder->viewField($node->get('translation_sync'));
      $output = $this->container->get('renderer')->renderRoot($build);
      $this->assertContains((string) TranslationSynchronisationFormatter::getSyncTypeLabel($node->get('translation_sync')->first()), (string) $output);
    }
  }

}
