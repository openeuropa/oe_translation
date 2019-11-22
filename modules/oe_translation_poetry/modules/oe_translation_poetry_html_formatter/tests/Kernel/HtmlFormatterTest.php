<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry_html_formatter\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the Poetry HTML Formatter.
 */
class HtmlFormatterTest extends TranslationKernelTestBase {

  use UserCreationTrait;

  /**
   * The job to test.
   *
   * @var \Drupal\tmgmt\JobInterface
   */
  protected $job;

  /**
   * The job item to test.
   *
   * @var \Drupal\tmgmt\JobItemInterface
   */
  protected $jobItem;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tmgmt_content',
    'tmgmt_test',
    'oe_translation_poetry_html_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $node_type->save();

    $field_storage_definition = [
      'field_name' => 'translatable_text_field',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
      'translatable' => TRUE,
    ];
    $field_storage = FieldStorageConfig::create($field_storage_definition);
    $field_storage->save();

    $field_definition = [
      'field_storage' => $field_storage,
      'bundle' => 'test_node_type',
    ];
    $field = FieldConfig::create($field_definition);
    $field->save();

    // Create a test translator.
    tmgmt_translator_auto_create($this->container->get('plugin.manager.tmgmt.translator')->getDefinition('test_translator'));
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'test_node_type',
      'title' => 'English title',
      'translatable_text_field' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
    ]);
    $node->save();

    $storage = $this->container->get('entity_type.manager')->getStorage('tmgmt_job');
    /** @var \Drupal\tmgmt\JobInterface $job */
    $job = $storage->create([
      'source_language' => 'en',
      'target_language' => 'de',
      'uid' => 0,
    ]);
    $job->translator = 'test_translator';
    $job->save();
    $this->job = $job;
    $this->jobItem = $job->addItem('content', $node->getEntityTypeId(), $node->id());
  }

  /**
   * Tests that the formatter exports a correctly formatted Poetry html.
   */
  public function testHtmlFormatterExport() {
    /** @var \Drupal\oe_translation_poetry_html_formatter\PoetryHtmlFormatter $formatter */
    $formatter = $this->container->get('oe_translation_poetry.html_formatter');

    /** @var \Drupal\Core\Render\Markup $export */
    $export = $formatter->export($this->job);
    $expected = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html');
    $this->assertEqual($export, $expected);
  }

  /**
   * Tests that the formatter can import a correctly formatted Poetry html.
   */
  public function testHtmlFormatterImport() {
    /** @var \Drupal\oe_translation_poetry_html_formatter\PoetryHtmlFormatter $formatter */
    $formatter = $this->container->get('oe_translation_poetry.html_formatter');
    $expected_data = $formatter->import(drupal_get_path('module', 'oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html', TRUE);

    // Let's get the data from the job_item and remove all the information that
    // doesn't get send to DGT.
    $data = $this->container->get('tmgmt.data')->filterTranslatable($this->jobItem->getData());
    $unflattened_data = $this->container->get('tmgmt.data')->unflatten($data);
    $excluded_fields = [
      '#translate',
      '#max_length',
      '#status',
      '#parent_label',
    ];
    foreach ($excluded_fields as $excluded_field) {
      unset($unflattened_data['title'][0]['value'][$excluded_field]);
      unset($unflattened_data['translatable_text_field'][0]['value'][$excluded_field]);
    }
    $actual_data = [$this->jobItem->id() => $unflattened_data];
    $this->assertEqual($actual_data, $expected_data);
  }

}
