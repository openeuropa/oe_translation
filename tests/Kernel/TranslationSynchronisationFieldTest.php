<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\oe_translation\Plugin\Field\FieldFormatter\TranslationSynchronisationFormatter;

/**
 * Tests the Translation Synchronisation field type and formatter.
 */
class TranslationSynchronisationFieldTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
      'no-values' => [
        'type' => '',
      ],
      'manual' => [
        'type' => 'manual',
        'configuration' => [],
      ],
      'automatic_languages' => [
        'type' => 'automatic',
        'configuration' => [
          'languages' => [
            'bg',
            'fr',
          ],
          'date' => NULL,
        ],
      ],
      'automatic_languages_date' => [
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

    foreach ($tests as $case => $values) {
      $node->set('translation_sync', $values);
      $node->save();
      $node_storage->resetCache();
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->load($node->id());
      if ($case === 'no-values') {
        $this->assertTrue($node->get('translation_sync')->isEmpty());
        continue;
      }

      $this->assertEquals($values, $node->get('translation_sync')->first()->getValue());
      $builder = $this->container->get('entity_type.manager')->getViewBuilder('node');
      $build = $builder->viewField($node->get('translation_sync'));
      $output = $this->container->get('renderer')->renderRoot($build);
      $this->assertContains((string) TranslationSynchronisationFormatter::getSyncTypeLabel($node->get('translation_sync')->first()), (string) $output);
    }

    // Test the languages validation.
    $tests = [
      'invalid' => [
        [
          'type' => 'automatic',
          'configuration' => [
            'languages' => [],
            'date' => $date->getTimestamp(),
          ],
        ],
      ],
      'valid' => [
        [
          'type' => 'manual',
          'configuration' => [],
        ],
        [
          'type' => 'automatic',
          'configuration' => [
            'languages' => ['fr'],
            'date' => $date->getTimestamp(),
          ],
        ],
        [
          'type' => 'automatic',
          'configuration' => [
            'languages' => ['fr'],
            'date' => NULL,
          ],
        ],
      ],
    ];

    foreach ($tests as $status => $cases) {
      foreach ($cases as $values) {
        $node->set('translation_sync', $values);
        $violations = $node->get('translation_sync')->validate();

        if ($status === 'valid') {
          $this->assertEquals(0, $violations->count());
          continue;
        }

        $this->assertEquals(1, $violations->count());
        $violation = $violations->get(0);
        $this->assertEqual('Select at least one language to be approved for synchronizing.', $violation->getMessage());
      }
    }
  }

}
