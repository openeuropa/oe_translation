<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_multivalue\Functional;

use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_multivalue\TranslationMultivalueColumnInstaller;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the multivalue translations.
 *
 * @group batch1
 */
class MultivalueTranslationsTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
    'menu_link_content',
    'views',
    'oe_translation_multivalue',
    'oe_translation_multivalue_test',
    'typed_link',
    'oe_content_timeline_field',
    'description_list_field',
    'link_description',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests that the field configuration affects the column creation.
   */
  public function testMultivalueFieldConfiguration(): void {
    $field_names = [
      'field_textfield',
      'field_address',
      'field_description_list',
      'field_link',
      'field_link_description',
      'field_timeline',
      'field_typed_link',
    ];

    $entity_field_manager = \Drupal::service('entity_field.manager');

    $schema = \Drupal::database()->schema();
    foreach ($field_names as $field_name) {
      $tables = [];
      foreach (['node__', 'node_revision__'] as $prefix) {
        $tables[] = $prefix . $field_name;
      }

      // Assert the table doesn't yet have the column.
      foreach ($tables as $table) {
        $this->assertFalse($schema->fieldExists($table, $field_name . '_translation_id'));
      }
      $field_definitions = $entity_field_manager->getFieldStorageDefinitions('node');
      $field_definition = $field_definitions[$field_name];
      $this->assertFalse(in_array('translation_id', $field_definition->getPropertyNames()));

      $storage = FieldStorageConfig::load('node.' . $field_name);
      $storage->setSetting('translation_multivalue', TRUE);
      $storage->save();

      // All the fields are already multivalue, so the column should now be
      // created.
      foreach ($tables as $table) {
        $this->assertTrue($schema->fieldExists($table, $field_name . '_translation_id'));
      }
      $entity_field_manager->clearCachedFieldDefinitions();
      $field_definitions = $entity_field_manager->getFieldStorageDefinitions('node');
      $field_definition = $field_definitions[$field_name];
      $this->assertTrue(in_array('translation_id', $field_definition->getPropertyNames()));

      // Change the cardinality to 1 and assert we no longer have the column.
      $storage = FieldStorageConfig::load('node.' . $field_name);
      $storage->setCardinality(1);
      $storage->save();

      foreach ($tables as $table) {
        $this->assertFalse($schema->fieldExists($table, $field_name . '_translation_id'));
      }

      $entity_field_manager->clearCachedFieldDefinitions();
      $field_definitions = $entity_field_manager->getFieldStorageDefinitions('node');
      $field_definition = $field_definitions[$field_name];
      $this->assertFalse(in_array('translation_id', $field_definition->getPropertyNames()));
    }
  }

  /**
   * Tests a case in which nodes were translated before this feature.
   *
   * @see LocalTranslationRequestForm::processTranslationData()
   */
  public function testLegacyMultivalueFieldTranslations(): void {
    // Mark a field as multivalue.
    $storage = FieldStorageConfig::load('node.field_textfield');
    $storage->setSetting('translation_multivalue', TRUE);
    $storage->save();

    $node = Node::create([
      'type' => 'multivalue',
      'title' => 'Translation node',
      'field_textfield' => [
        'Value 1',
        'Value 2',
      ],
    ]);

    $node->save();
    $translation = $node->addTranslation('fr', [
      'title' => 'Translation node FR',
      'field_textfield' => [
        'Value 1 FR',
        'Value 2 FR',
      ],
    ] + $node->toArray());
    $translation->save();
    // Clear the field tables of the translation ID to mimic the fact that they
    // were created before this feature.
    \Drupal::database()->update('node__field_textfield')
      ->fields([
        'field_textfield_translation_id' => NULL,
      ])->execute();
    \Drupal::database()->update('node_revision__field_textfield')
      ->fields([
        'field_textfield_translation_id' => NULL,
      ])->execute();

    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);
    $translation = $node->getTranslation('fr');
    $this->assertNull($node->get('field_textfield')->get(0)->translation_id);
    $this->assertNull($node->get('field_textfield')->get(1)->translation_id);
    $this->assertNull($translation->get('field_textfield')->get(0)->translation_id);
    $this->assertNull($translation->get('field_textfield')->get(1)->translation_id);

    // Now make a change to the node and assert that the source values had
    // their translation IDs set, but not yet the translations.
    $node->set('title', 'Translation node update');
    $node->save();
    $node = $this->drupalGetNodeByTitle('Translation node update', TRUE);
    $translation = $node->getTranslation('fr');
    $this->assertNotNull($node->get('field_textfield')->get(0)->translation_id);
    $this->assertNotNull($node->get('field_textfield')->get(1)->translation_id);
    $this->assertNull($translation->get('field_textfield')->get(0)->translation_id);
    $this->assertNull($translation->get('field_textfield')->get(1)->translation_id);

    // Go translate the node and assert that the existing FR values are
    // pre-filled.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    $this->assertSession()->fieldValueEquals('field_textfield|0|value[translation]', 'Value 1 FR');
    $this->assertSession()->fieldValueEquals('field_textfield|1|value[translation]', 'Value 2 FR');
  }

  /**
   * Tests the translation of multivalue fields using the API.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testMultivalueFieldTranslationsApi(): void {
    // Enable the multivalue translation on our fields.
    $field_names = [
      'field_textfield',
      'field_address',
      'field_description_list',
      'field_link',
      'field_link_description',
      'field_timeline',
      'field_typed_link',
    ];

    foreach ($field_names as $field_name) {
      $storage = FieldStorageConfig::load('node.' . $field_name);
      $storage->setSetting('translation_multivalue', TRUE);
      $storage->save();
    }
    $storage = FieldStorageConfig::load('paragraph.field_textfield_paragraph');
    $storage->setSetting('translation_multivalue', TRUE);
    $storage->save();

    // Create a node with all the values, each field with 2 deltas.
    $paragraph = Paragraph::create([
      'type' => 'multivalue_paragraph',
      'field_textfield_paragraph' => [
        'Value 1',
        'Value 2',
      ],
    ]);
    $paragraph->save();

    $node = Node::create([
      'type' => 'multivalue',
      'title' => 'Translation node',
      'field_paragraphs' => [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ],
      'field_textfield' => [
        'Value 1',
        'Value 2',
      ],
      'field_address' => [
        [
          'country_code' => 'BE',
          'given_name' => 'The first name 1',
          'family_name' => 'The last name 1',
          'locality' => 'Brussels',
          'postal_code' => '1000',
          'address_line1' => 'The street name 1',
        ],
        [
          'country_code' => 'BE',
          'given_name' => 'The first name 2',
          'family_name' => 'The last name 2',
          'locality' => 'Brussels',
          'postal_code' => '1000',
          'address_line1' => 'The street name 2',
        ],
      ],
      'field_description_list' => [
        [
          'term' => 'term one',
          'description' => 'Description 1',
          'format' => 'plain_text',
        ],
        [
          'term' => 'term two',
          'description' => 'Description 2',
          'format' => 'plain_text',
        ],
      ],
      'field_link_description' => [
        [
          'uri' => 'http://example.com/one',
          'description' => 'Description 1',
        ],
        [
          'uri' => 'http://example.com/two',
          'description' => 'Description 2',
        ],
      ],
      'field_link' => [
        [
          'uri' => 'http://example.com/one',
        ],
        [
          'uri' => 'http://example.com/two',
        ],
      ],
      'field_timeline' => [
        [
          'label' => 'label one',
          'title' => 'title one',
          'body' => 'body one',
          'format' => 'plain_text',
        ],
        [
          'label' => 'label two',
          'title' => 'title two',
          'body' => 'body two',
          'format' => 'plain_text',
        ],
      ],
      'field_typed_link' => [
        [
          'uri' => 'http://example.com/one',
          'link_type' => 'value_one',
        ],
        [
          'uri' => 'http://example.com/two',
          'link_type' => 'value_two',
        ],
      ],
    ]);

    $node->save();
    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);

    // Start to keep track of the translation IDs that were created for each
    // of the values.
    $translation_ids = [];
    foreach ($field_names as $field_name) {
      $values = $node->get($field_name)->getValue();
      foreach ($values as $value) {
        $translation_ids[$field_name]['en'][$value['translation_id']] = $value;
      }
    }
    $values = $node->get('field_paragraphs')->entity->get('field_textfield_paragraph')->getValue();
    foreach ($values as $value) {
      $translation_ids['field_textfield_paragraph']['en'][$value['translation_id']] = $value;
    }

    $this->drupalGet($node->toUrl());

    // Translate the node.
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();

    // Translate the values.
    $tables = $this->getSession()->getPage()->findAll('css', 'table.responsive-enabled');
    foreach ($tables as $table) {
      $textarea = $table->find('xpath', '//td[2]//textarea');
      $value = $textarea->getValue();
      if (in_array($value, ['BE', 'Brussels', '1000'])) {
        continue;
      }
      $textarea->setValue($value . '/FR');
    }
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);
    $this->assertTrue($node->hasTranslation('fr'));

    $translation = $node->getTranslation('fr');
    foreach ($field_names as $field_name) {
      $values = $translation->get($field_name)->getValue();
      foreach ($values as $value) {
        $translation_ids[$field_name]['fr'][$value['translation_id']] = $value;
      }
    }
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache();
    $values = $node->get('field_paragraphs')->entity->getTranslation('fr')->get('field_textfield_paragraph')->getValue();
    foreach ($values as $value) {
      $translation_ids['field_textfield_paragraph']['fr'][$value['translation_id']] = $value;
    }

    // Assert that the translation IDs are the same for both the EN and FR
    // deltas.
    foreach ($translation_ids as $field_name => $data) {
      $this->assertEquals(array_keys($data['en']), array_keys($data['fr']));
    }

    // Reorder the values on all the fields.
    $paragraph = $node->get('field_paragraphs')->entity;
    $paragraph->setNewRevision();
    $values = $paragraph->get('field_textfield_paragraph')->getValue();
    $values = array_reverse($values);
    $paragraph->set('field_textfield_paragraph', $values);
    $paragraph->save();
    foreach ($field_names as $field_name) {
      $values = $node->get($field_name)->getValue();
      $values = array_reverse($values);
      $node->set($field_name, $values);
      $node->set('field_paragraphs', [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ]);
      $node->setNewRevision();
      $node->save();
    }

    $this->drupalGet($node->toUrl());

    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);
    $new_translation_ids = $this->createTranslationIdMap($node, $field_names);

    // Build an array of array keys to use for asserting the value matches.
    $value_test_keys = [
      'field_textfield' => 'value',
      'field_address' => 'address_line1',
      'field_description_list' => 'term',
      'field_link' => 'uri',
      'field_link_description' => 'uri',
      'field_timeline' => 'label',
      'field_typed_link' => 'uri',
    ];

    // Assert that now that we have reordered each field, the deltas are no
    // longer matching, but the translation IDs still are representative of
    // the connection between the original value and the translation.
    foreach ($field_names as $field_name) {
      // First we assert the expectation that the EN delta 0's translation
      // ID is now matching the delta 1's translation ID because the translation
      // hasn't yet been updated to reflect the change in delta.
      $this->assertNotNull($new_translation_ids[$field_name]['en'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['en'][1]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][0]['translation_id'], $new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1]['translation_id'], $new_translation_ids[$field_name]['fr'][0]['translation_id']);

      // Next, assert that the values are mapped by the translation IDs (just
      // like above, with the deltas not matching).
      $key = $value_test_keys[$field_name];
      $this->assertEquals($new_translation_ids[$field_name]['en'][0][$key] . '/FR', $new_translation_ids[$field_name]['fr'][1][$key]);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1][$key] . '/FR', $new_translation_ids[$field_name]['fr'][0][$key]);
    }

    // Translate the node again and assert that the translation values are
    // correctly pre-filled, not based on the delta, but based on the matching
    // translation IDs.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    foreach ($field_names as $field_name) {
      $suffix = '';
      $key = $value_test_keys[$field_name];
      foreach ([0, 1] as $delta) {
        $source = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[source]%s"]', $field_name, $delta, $key, $suffix))->getValue();
        $translation = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[translation]%s"]', $field_name, $delta, $key, $suffix))->getValue();
        $this->assertEquals($source . '/FR', $translation);
      }
    }

    // Save the translation.
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);

    // Assert that now the deltas are in line with the values and the
    // translation IDs.
    $new_translation_ids = $this->createTranslationIdMap($node, $field_names);

    foreach ($field_names as $field_name) {
      $this->assertNotNull($new_translation_ids[$field_name]['en'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['en'][1]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][0]['translation_id'], $new_translation_ids[$field_name]['fr'][0]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1]['translation_id'], $new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $key = $value_test_keys[$field_name];
      $this->assertEquals($new_translation_ids[$field_name]['en'][0][$key] . '/FR', $new_translation_ids[$field_name]['fr'][0][$key]);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1][$key] . '/FR', $new_translation_ids[$field_name]['fr'][1][$key]);
    }

    // Start a new translation and assert the values are pre-filled correctly
    // still.
    $this->drupalGet($node->toUrl());
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    foreach ($field_names as $field_name) {
      $suffix = '';
      $key = $value_test_keys[$field_name];
      foreach ([0, 1] as $delta) {
        $source = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[source]%s"]', $field_name, $delta, $key, $suffix))->getValue();
        $translation = $this->getSession()->getPage()->find('xpath', sprintf('//table//td//textarea[@name="%s|%s|%s[translation]%s"]', $field_name, $delta, $key, $suffix))->getValue();
        $this->assertEquals($source . '/FR', $translation);
      }
    }

    $new_translation_ids = $this->createTranslationIdMap($node, $field_names);
    foreach ($field_names as $field_name) {
      $this->assertNotNull($new_translation_ids[$field_name]['en'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['en'][1]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][0]['translation_id']);
      $this->assertNotNull($new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][0]['translation_id'], $new_translation_ids[$field_name]['fr'][0]['translation_id']);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1]['translation_id'], $new_translation_ids[$field_name]['fr'][1]['translation_id']);
      $key = $value_test_keys[$field_name];
      $this->assertEquals($new_translation_ids[$field_name]['en'][0][$key] . '/FR', $new_translation_ids[$field_name]['fr'][0][$key]);
      $this->assertEquals($new_translation_ids[$field_name]['en'][1][$key] . '/FR', $new_translation_ids[$field_name]['fr'][1][$key]);
    }
  }

  /**
   * Tests that we can update the configuration of fields that contain data.
   */
  public function testFieldConfigurationUpdate(): void {
    $entity_field_manager = \Drupal::service('entity_field.manager');

    $node = Node::create([
      'type' => 'multivalue',
      'title' => 'Translation node',
      'field_textfield' => [
        'Value 1',
        'Value 2',
      ],
    ]);
    $node->save();

    $database = \Drupal::database();
    $schema = $database->schema();
    $tables = [];
    $field_name = 'field_textfield';
    foreach (['node__', 'node_revision__'] as $prefix) {
      $tables[] = $prefix . $field_name;
    }

    // Assert the table doesn't yet have the column but there are values in the
    // tables.
    foreach ($tables as $table) {
      $this->assertFalse($schema->fieldExists($table, $field_name . '_translation_id'));
      $this->assertNotEmpty($database->select($table)->fields($table)->execute()->fetchAll());
    }
    $field_definitions = $entity_field_manager->getFieldStorageDefinitions('node');
    $field_definition = $field_definitions[$field_name];
    $this->assertFalse(in_array('translation_id', $field_definition->getPropertyNames()));

    // Install the field column.
    TranslationMultivalueColumnInstaller::installColumn('node.field_textfield');

    // Assert that we have the column, the field definition update and the
    // content is still in the field tables.
    foreach ($tables as $table) {
      $this->assertTrue($schema->fieldExists($table, $field_name . '_translation_id'));
      $this->assertNotEmpty($database->select($table)->fields($table)->execute()->fetchAll());
    }
    $entity_field_manager->clearCachedFieldDefinitions();
    $field_definitions = $entity_field_manager->getFieldStorageDefinitions('node');
    $field_definition = $field_definitions[$field_name];
    $this->assertTrue(in_array('translation_id', $field_definition->getPropertyNames()));
    $node = $this->drupalGetNodeByTitle('Translation node', TRUE);
    $this->assertEquals([
      [
        'value' => 'Value 1',
        'translation_id' => NULL,
      ],
      [
        'value' => 'Value 2',
        'translation_id' => NULL,
      ],
    ], $node->get('field_textfield')->getValue());
  }

  /**
   * Creates an array of the field values and their translation IDs.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $field_names
   *   The field names.
   *
   * @return array
   *   The value map.
   */
  protected function createTranslationIdMap(NodeInterface $node, array $field_names): array {
    $translation_ids = [];
    foreach ($field_names as $field_name) {
      $values = $node->get($field_name)->getValue();
      foreach ($values as $value) {
        $translation_ids[$field_name]['en'][] = $value;
      }
    }
    $values = $node->get('field_paragraphs')->entity->get('field_textfield_paragraph')->getValue();
    foreach ($values as $value) {
      $translation_ids['field_textfield_paragraph']['en'][] = $value;
    }
    $translation = $node->getTranslation('fr');
    foreach ($field_names as $field_name) {
      $values = $translation->get($field_name)->getValue();
      foreach ($values as $value) {
        $translation_ids[$field_name]['fr'][] = $value;
      }
    }
    \Drupal::entityTypeManager()->getStorage('paragraph')->resetCache();
    $values = $node->get('field_paragraphs')->entity->getTranslation('fr')->get('field_textfield_paragraph')->getValue();
    foreach ($values as $value) {
      $translation_ids['field_textfield_paragraph']['fr'] = $value;
    }

    return $translation_ids;
  }

}
