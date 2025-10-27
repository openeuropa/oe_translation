<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;

/**
 * Tests the Metatags field processor.
 *
 * @group batch1
 */
class MetatagsFieldProcessorTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installConfig(['metatag']);

    FieldStorageConfig::create([
      'field_name' => 'metatags_field',
      'entity_type' => 'node',
      'type' => 'metatag',
      'cardinality' => 1,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'metatags_field',
      'bundle' => 'page',
      'label' => 'Metatags',
    ])->save();
  }

  /**
   * Tests the metatags field processor.
   */
  public function testMetatagsFieldProcessor() {
    // Create a node to be translated.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node',
      'metatags_field' => [
        'value' => Json::encode([
          'description' => 'The description',
          'robots' => 'noindex,nofollow',
          'referer' => 'origin',
          'rights' => 'Details about intellectual property',
        ]),
      ],
    ]);
    $node->save();

    // Extract the translatable data.
    $data = $this->translationManager->extractData($node);
    // Test the expected structure of the metatags field.
    $expected_field_data = [
      'basic' => [
        '#label' => 'Basic tags',
        'description' => [
          '#translate' => TRUE,
          '#text' => 'The description',
          '#label' => 'Description',
        ],
      ],
      'advanced' => [
        '#label' => 'Advanced',
        'robots' => [
          '#translate' => FALSE,
          '#text' => 'noindex,nofollow',
          '#label' => 'Robots',
        ],
        'rights' => [
          '#translate' => TRUE,
          '#text' => 'Details about intellectual property',
          '#label' => 'Rights',
        ],
      ],
      '#label' => 'Metatags',
    ];
    $this->assertEquals($expected_field_data, $data['metatags_field']);

    // Translate the data.
    $data['metatags_field']['basic']['description']['#translation']['#text'] = 'The description FR';
    $data['metatags_field']['advanced']['rights']['#translation']['#text'] = 'Details about intellectual property FR';
    $this->translationManager->saveData($data, $node, 'fr');

    $translation = $node->getTranslation('fr');
    $translated_meta_tags = Json::decode($translation->get('metatags_field')->value);
    $expected_meta_tags = [
      'description' => 'The description FR',
      'robots' => 'noindex,nofollow',
      'rights' => 'Details about intellectual property FR',
    ];
    $this->assertEquals($expected_meta_tags, $translated_meta_tags);

    // Create a node without metatags.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node 2',
    ]);
    $node->save();
    $node->save();

    // Extract the translatable data.
    $data = $this->translationManager->extractData($node);
    $this->assertEquals([], $data['metatags_field']);
  }

}
