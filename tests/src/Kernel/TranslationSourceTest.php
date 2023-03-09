<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Test the TranslationSourceManager.
 *
 * @group batch1
 */
class TranslationSourceTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'filter',
    'views',
  ];

  /**
   * A node referencing entities.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $referencingNode;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['views']);

    // Create a text filter.
    $filter = FilterFormat::create([
      'format' => 'unallowed_format',
      'name' => 'Unallowed Format',
    ]);
    $filter->save();

    // Set the allowed formats.
    $this->config('oe_translation.settings')
      ->set('translation_source_allowed_formats', ['text_plain'])
      ->save();

    // Add a text field for the Page bundle.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'translatable_text_field',
      'type' => 'text',
      'cardinality' => 3,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'translatable_text_field',
      'bundle' => 'page',
      'label' => 'Translatable text field',
      'translatable' => TRUE,
    ])->save();
    // Create an image field.
    FieldStorageConfig::create([
      'field_name' => 'image_field',
      'entity_type' => 'node',
      'type' => 'image',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'image_field',
      'bundle' => 'page',
      'label' => 'Image field',
      'third_party_settings' => [
        'content_translation' => [
          'translation_sync' => [
            'file' => FALSE,
            'alt' => FALSE,
            'title' => 'title',
          ],
        ],
      ],
    ])->save();
    // Create an image file.
    \Drupal::service('file_system')->copy(DRUPAL_ROOT . '/core/misc/druplicon.png', 'public://example.jpg');
    $this->image_file = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $this->image_file->save();
    // Mark the paragraph bundles translatable.
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_paragraph_type', TRUE);
    \Drupal::service('content_translation.manager')
      ->setEnabled('paragraph', 'demo_inner_paragraph_type', TRUE);

    // Mark the test entity reference field as embeddable.
    $this->config('oe_translation.settings')
      ->set('translation_source_embedded_fields', [
        'node' => [
          'ott_content_reference' => TRUE,
        ],
      ])
      ->save();

    // Create paragraphs to reference and translate.
    $grandchild_paragraph = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'grandchild field value 1',
    ]);
    $grandchild_paragraph->save();
    $child_paragraph = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'child field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $grandchild_paragraph->id(),
          'target_revision_id' => $grandchild_paragraph->getRevisionId(),
        ],
      ],
    ]);
    $child_paragraph->save();
    $top_paragraph_one = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $child_paragraph->id(),
          'target_revision_id' => $child_paragraph->getRevisionId(),
        ],
      ],
    ]);
    $top_paragraph_one->save();
    $top_paragraph_two = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 2',
    ]);
    $top_paragraph_two->save();
    // Create a node to be referenced.
    $referenced_node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Referenced node',
    ]);
    $referenced_node->save();
    // Create a node to reference another node and paragraphs.
    $this->referencingNode = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Node referencing entities',
      'ott_top_level_paragraphs' => [
        [
          'target_id' => $top_paragraph_one->id(),
          'target_revision_id' => $top_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $top_paragraph_two->id(),
          'target_revision_id' => $top_paragraph_two->getRevisionId(),
        ],
      ],
      'ott_content_reference' => $referenced_node->id(),
    ]);
    $this->referencingNode->save();
  }

  /**
   * Tests the text and image fields node translation.
   */
  public function testNodeTranslation(): void {
    // Create a node to be translated.
    $node = Node::create([
      'type' => 'page',
      'title' => 'Test node',
    ]);
    $node->set('body', [
      'value' => 'Body text',
      'summary' => 'Summary text',
      'format' => 'text_plain',
    ]);
    $node->set('translatable_text_field', [
      [
        'value' => '1st text value',
        'format' => 'text_plain',
      ],
      [
        'value' => '2nd text value',
        'format' => 'unallowed_format',
      ],
    ]);
    $node->set('image_field', [
      'target_id' => $this->image_file->id(),
      'alt' => 'Alt text',
      'title' => 'Image title',
    ])->save();

    // Extract the translatable data.
    $data = $this->translationManager->extractData($node);

    // Assert Title field data.
    $this->assertEquals('Title', $data['title']['#label']);
    $this->assertFalse(isset($data['title'][0]['#label']));
    $this->assertFalse(isset($data['title'][0]['value']['#label']));
    $this->assertEquals($node->getTitle(), $data['title'][0]['value']['#text']);
    $this->assertTrue($data['title'][0]['value']['#translate']);
    // Assert Body field data.
    $this->assertEquals('Body', $data['body']['#label']);
    $this->assertEquals('Text', (string) $data['body'][0]['value']['#label']);
    $this->assertEquals($node->body->value, $data['body'][0]['value']['#text']);
    $this->assertTrue($data['body'][0]['value']['#translate']);
    $this->assertEquals('text_plain', $data['body'][0]['value']['#format']);
    $this->assertEquals('Summary', (string) $data['body'][0]['summary']['#label']);
    $this->assertEquals($node->body->summary, $data['body'][0]['summary']['#text']);
    $this->assertTrue($data['body'][0]['summary']['#translate']);
    $this->assertEquals('Text format', (string) $data['body'][0]['format']['#label']);
    $this->assertEquals($node->body->format, $data['body'][0]['format']['#text']);
    $this->assertFalse($data['body'][0]['format']['#translate']);
    $this->assertFalse(isset($data['body'][0]['processed']));
    // Assert Translatable text field first item data.
    $this->assertEquals('Translatable text field', $data['translatable_text_field']['#label']);
    $this->assertEquals('Delta #0', $data['translatable_text_field'][0]['#label']);
    $this->assertFalse(isset($data['translatable_text_field'][0]['value']['#label']));
    $this->assertEquals($node->translatable_text_field->value, $data['translatable_text_field'][0]['value']['#text']);
    $this->assertTrue($data['translatable_text_field'][0]['value']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][0]['format']['#label']));
    $this->assertEquals('text_plain', $data['translatable_text_field'][0]['value']['#format']);
    $this->assertEquals($node->translatable_text_field->format, $data['translatable_text_field'][0]['format']['#text']);
    $this->assertFalse($data['translatable_text_field'][0]['format']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][0]['processed']));
    // Assert the second item.
    $this->assertEquals('Delta #1', $data['translatable_text_field'][1]['#label']);
    $this->assertFalse(isset($data['translatable_text_field'][1]['value']['#label']));
    $this->assertEquals($node->translatable_text_field[1]->value, $data['translatable_text_field'][1]['value']['#text']);
    $this->assertFalse($data['translatable_text_field'][1]['value']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][1]['format']['#label']));
    $this->assertEquals($node->translatable_text_field[1]->format, $data['translatable_text_field'][1]['format']['#text']);
    $this->assertFalse($data['translatable_text_field'][1]['format']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][1]['processed']));
    // Assert image field data.
    $image_item = $data['image_field'][0];
    $this->assertEquals('Image field', $data['image_field']['#label']);
    $this->assertFalse(isset($image_item['#label']));
    $this->assertFalse($image_item['target_id']['#translate']);
    $this->assertFalse($image_item['width']['#translate']);
    $this->assertFalse($image_item['height']['#translate']);
    // The alt column is not translatable but the title is.
    $this->assertFalse($image_item['alt']['#translate']);
    $this->assertEquals($image_item['alt']['#text'], $node->image_field->alt);
    // Since only one translatable column remained, the labels have been
    // removed.
    $this->assertFalse(isset($image_item['title']['#label']));
    $this->assertFalse(isset($image_item['alt']['#label']));
    $this->assertTrue($image_item['title']['#translate']);
    $this->assertEquals($node->image_field->title, $image_item['title']['#text']);

    // Translate the data.
    $data['title'][0]['value']['#translation']['#text'] = 'Test node FR';
    $data['body'][0]['value']['#translation']['#text'] = 'Body text FR';
    $data['body'][0]['summary']['#translation']['#text'] = 'Summary text FR';
    $data['translatable_text_field'][0]['value']['#translation']['#text'] = '1st text value FR';
    $data['image_field'][0]['title']['#translation']['#text'] = 'Image title FR';
    $this->translationManager->saveData($data, $node, 'fr');

    // @todo assert it did not make a new revision.
    // Check that the translation was saved correctly on the entity.
    $translation = $node->getTranslation('fr');
    $this->assertEquals($data['title'][0]['value']['#translation']['#text'], $translation->getTitle());
    $this->assertEquals($data['body'][0]['value']['#translation']['#text'], $translation->body->value);
    $this->assertEquals($data['body'][0]['summary']['#translation']['#text'], $translation->body->summary);
    $this->assertEquals($data['translatable_text_field'][0]['value']['#translation']['#text'], $translation->translatable_text_field->value);
    $this->assertEquals($data['image_field'][0]['title']['#translation']['#text'], $translation->image_field->title);

    // Add another value to the Translatable text field.
    $node = $node->getTranslation('en');
    $node->translatable_text_field->appendItem([
      'value' => '3rd text value',
      'format' => 'text_plain',
    ]);
    $node->save();
    $data = $this->translationManager->extractData($node);
    // Assert the data is saved correctly.
    $this->assertEquals('Translatable text field', $data['translatable_text_field']['#label']);
    $this->assertEquals('Delta #2', $data['translatable_text_field'][2]['#label']);
    $this->assertFalse(isset($data['translatable_text_field'][2]['value']['#label']));
    $this->assertEquals($node->translatable_text_field[2]->value, $data['translatable_text_field'][2]['value']['#text']);
    $this->assertTrue($data['translatable_text_field'][2]['value']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][2]['format']['#label']));
    $this->assertEquals('text_plain', $data['translatable_text_field'][2]['value']['#format']);
    $this->assertEquals($node->translatable_text_field->format, $data['translatable_text_field'][2]['format']['#text']);
    $this->assertFalse($data['translatable_text_field'][2]['format']['#translate']);
    $this->assertFalse(isset($data['translatable_text_field'][2]['processed']));

    // Translate the data again.
    $data['translatable_text_field'][2]['value']['#translation']['#text'] = '3rd text value FR';
    $this->translationManager->saveData($data, $node, 'fr');
    $translation = $node->getTranslation('fr');
    $this->assertEquals($data['translatable_text_field'][2]['value']['#translation']['#text'], $translation->translatable_text_field[2]->value);
  }

  /**
   * Tests the translation of referenced entities.
   *
   * @todo assert non-embeddable references are not included.
   */
  public function testNodeReferencingEntities(): void {
    $data = $this->translationManager->extractData($this->referencingNode);
    // Assert paragraph's field data is saved correctly.
    $this->assertEquals('Top level paragraphs', $data['ott_top_level_paragraphs']['#label']);
    // Assert first paragraph.
    $paragraph1 = &$data['ott_top_level_paragraphs'][0];
    $this->assertEquals('Delta #0', $paragraph1['#label']);
    $this->assertEquals('Top level paragraph field', $paragraph1['entity']['ott_top_level_paragraph_field']['#label']);
    $this->assertEquals('top field value 1', $paragraph1['entity']['ott_top_level_paragraph_field'][0]['value']['#text']);
    $this->assertTrue($paragraph1['entity']['ott_top_level_paragraph_field'][0]['value']['#translate']);
    // Assert child and grandchild paragraphs.
    $inner_paragraphs = &$paragraph1['entity']['ott_inner_paragraphs'];
    $this->assertEquals('Inner Paragraphs', $inner_paragraphs['#label']);
    $this->assertEquals('Inner paragraph field', $inner_paragraphs[0]['entity']['ott_inner_paragraph_field']['#label']);
    $this->assertEquals('child field value 1', $inner_paragraphs[0]['entity']['ott_inner_paragraph_field'][0]['value']['#text']);
    $this->assertTrue($inner_paragraphs[0]['entity']['ott_inner_paragraph_field'][0]['value']['#translate']);
    $this->assertEquals('Inner Paragraphs', $inner_paragraphs[0]['entity']['ott_inner_paragraphs']['#label']);
    $this->assertEquals('Inner paragraph field', $inner_paragraphs[0]['entity']['ott_inner_paragraphs'][0]['entity']['ott_inner_paragraph_field']['#label']);
    $this->assertEquals('grandchild field value 1', $inner_paragraphs[0]['entity']['ott_inner_paragraphs'][0]['entity']['ott_inner_paragraph_field'][0]['value']['#text']);
    $this->assertTrue($inner_paragraphs[0]['entity']['ott_inner_paragraphs'][0]['entity']['ott_inner_paragraph_field'][0]['value']['#translate']);
    // Assert second paragraph.
    $paragraph2 = &$data['ott_top_level_paragraphs'][1];
    $this->assertEquals('Delta #1', $paragraph2['#label']);
    $this->assertEquals('Top level paragraph field', $paragraph2['entity']['ott_top_level_paragraph_field']['#label']);
    $this->assertEquals('top field value 2', $paragraph2['entity']['ott_top_level_paragraph_field'][0]['value']['#text']);
    $this->assertTrue($paragraph2['entity']['ott_top_level_paragraph_field'][0]['value']['#translate']);
    // Assert referenced node.
    $this->assertEquals('Content reference', $data['ott_content_reference']['#label']);
    $this->assertEquals('Title', $data['ott_content_reference'][0]['entity']['title']['#label']);
    $this->assertEquals('Referenced node', $data['ott_content_reference'][0]['entity']['title'][0]['value']['#text']);
    $this->assertTrue($data['ott_content_reference'][0]['entity']['title'][0]['value']['#translate']);

    // @todo assert we have the #entity_bundle and #entity_type in the data.
    // Translate the referenced data.
    $paragraph1['entity']['ott_top_level_paragraph_field'][0]['value']['#translation']['#text'] = 'top field value 1 FR';
    $inner_paragraphs[0]['entity']['ott_inner_paragraph_field'][0]['value']['#translation']['#text'] = 'child field value 1 FR';
    $inner_paragraphs[0]['entity']['ott_inner_paragraphs'][0]['entity']['ott_inner_paragraph_field'][0]['value']['#translation']['#text'] = 'grandchild field value 1 FR';
    $paragraph2['entity']['ott_top_level_paragraph_field'][0]['value']['#translation']['#text'] = 'top field value 2 FR';
    $data['ott_content_reference'][0]['entity']['title'][0]['value']['#translation']['#text'] = 'Referenced node FR';
    $this->translationManager->saveData($data, $this->referencingNode, 'fr');

    // Retrieve the translation and assert the values.
    $translation = $this->referencingNode->getTranslation('fr');
    $paragraph1 = $data['ott_top_level_paragraphs'][0];
    $inner_paragraphs = &$paragraph1['entity']['ott_inner_paragraphs'];
    $paragraph2 = $data['ott_top_level_paragraphs'][1];
    // Assert referenced node.
    $this->assertEquals($data['ott_content_reference'][0]['entity']['title'][0]['value']['#translation']['#text'], $translation->ott_content_reference[0]->entity->getTranslation('fr')->getTitle());
    // Assert first paragraph.
    $this->assertEquals($paragraph1['entity']['ott_top_level_paragraph_field'][0]['value']['#translation']['#text'], $translation->ott_top_level_paragraphs[0]->entity->getTranslation('fr')->ott_top_level_paragraph_field[0]->value);
    // Assert child of first paragraph.
    $this->assertEquals($inner_paragraphs[0]['entity']['ott_inner_paragraph_field'][0]['value']['#translation']['#text'], $translation->ott_top_level_paragraphs[0]->entity->getTranslation('fr')->ott_inner_paragraphs->entity->getTranslation('fr')->ott_inner_paragraph_field->value);
    // Assert grandchild of child.
    $this->assertEquals($inner_paragraphs[0]['entity']['ott_inner_paragraphs'][0]['entity']['ott_inner_paragraph_field'][0]['value']['#translation']['#text'], $translation->ott_top_level_paragraphs[0]->entity->getTranslation('fr')->ott_inner_paragraphs->entity->getTranslation('fr')->ott_inner_paragraphs->entity->getTranslation('fr')->ott_inner_paragraph_field->value);
    // Assert second paragraph.
    $this->assertEquals($paragraph2['entity']['ott_top_level_paragraph_field'][0]['value']['#translation']['#text'], $translation->ott_top_level_paragraphs[1]->entity->getTranslation('fr')->ott_top_level_paragraph_field[0]->value);
  }

}
