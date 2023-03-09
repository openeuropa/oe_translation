<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\node\Entity\Node;
use Drupal\oe_translation\TranslationSourceFieldProcessor\PathFieldProcessor;
use Drupal\pathauto\Entity\PathautoPattern;

/**
 * Tests the Path field processor.
 *
 * @group batch1
 */
class PathFieldProcessorTest extends TranslationKernelTestBase {

  /**
   * Tests the path integration.
   */
  public function testNoPathauto(): void {
    // Assert that the Path field processor is used on path fields.
    $definition = $this->container->get('plugin.manager.field.field_type')->getDefinition('path');
    $this->assertEquals($definition['oe_translation_source_field_processor'], PathFieldProcessor::class);

    // Create a node to be translated.
    $node = Node::create([
      'langcode' => 'en',
      'uid' => 1,
      'type' => 'page',
      'title' => 'Test node',
      'path' => [
        'alias' => '/en-test-node',
        'langcode' => 'en',
      ],
    ]);
    $node->save();

    // Extract the translatable data.
    $data = $this->translationManager->extractData($node);
    // Test the expected structure of the path field.
    $expected_field_data = [
      '#label' => 'URL alias',
      0 => [
        'alias' => [
          '#label' => 'Path alias',
          '#text' => '/en-test-node',
          '#translate' => TRUE,
        ],
        'pid' => [
          '#label' => 'Path id',
          '#text' => '1',
          '#translate' => FALSE,
        ],
        'langcode' => [
          '#label' => 'Language Code',
          '#text' => 'en',
          '#translate' => FALSE,
        ],
      ],
    ];
    $this->assertEquals($expected_field_data, $data['path']);

    // Translate the data.
    $data['path'][0]['alias']['#translation']['#text'] = '/fr-test-node';
    $data['path'][0]['langcode']['#translation']['#text'] = 'fr';
    $this->translationManager->saveData($data, $node, 'fr');
    // Assert the translation.
    $translation = $node->getTranslation('fr');
    $this->assertEquals('/fr-test-node', $translation->get('path')->alias);
    $this->assertEquals('fr', $translation->get('path')->langcode);
    $this->assertNotEquals($node->get('path')->pid, $translation->get('path')->pid);
  }

  /**
   * Tests the pathauto integration.
   */
  public function testPathauto(): void {
    // Create a pathauto pattern.
    $pattern = PathautoPattern::create([
      'id' => 'test_pattern',
      'type' => 'canonical_entities:node',
      'pattern' => '/[node:langcode]-[node:title]',
      'weight' => 0,
    ]);
    $pattern->save();
    \Drupal::service('router.builder')->rebuild();
    // Create a node with a path.
    $values = [
      'langcode' => 'en',
      'type' => 'page',
      'uid' => 1,
      'title' => 'Test node',
    ];
    $node = Node::create($values);
    $node->save();

    $node = Node::load($node->id());

    // Extract the translatable data.
    $data = $this->translationManager->extractData($node);
    // Test the expected structure of the path field.
    $expected_field_data = [
      '#label' => 'URL alias',
      0 => [
        'alias' => [
          '#label' => 'Path alias',
          '#text' => '/en-test-node',
          '#translate' => FALSE,
        ],
        'pid' => [
          '#label' => 'Path id',
          '#text' => '1',
          '#translate' => FALSE,
        ],
        'langcode' => [
          '#label' => 'Language Code',
          '#text' => 'en',
          '#translate' => FALSE,
        ],
      ],
    ];
    $this->assertEquals($expected_field_data, $data['path']);

    // Translate the data.
    $this->translationManager->saveData($data, $node, 'fr');
    // Assert the translation was saved correctly.
    $node = Node::load($node->id());
    $translation = $node->getTranslation('fr');
    $this->assertEquals('/fr-test-node', $translation->get('path')->alias);
    $this->assertEquals('fr', $translation->get('path')->langcode);
    $this->assertNotEquals($node->get('path')->pid, $translation->get('path')->pid);
  }

}
