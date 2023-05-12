<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry_html_formatter\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
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
    'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

    $this->installConfig(['filter']);

    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $node_type->save();

    // Add a formatted field to the content type.
    $field_storage_definition = [
      'field_name' => 'translatable_text_field',
      'entity_type' => 'node',
      'type' => 'text',
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

    // Add a plain text formatted field to the content type.
    $field_storage_definition = [
      'field_name' => 'translatable_text_field_plain',
      'entity_type' => 'node',
      'type' => 'text',
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

    // Create a format for the content.
    FilterFormat::create([
      'format' => 'html',
      'name' => 'Html',
      'weight' => 1,
      'filters' => [],
    ])->save();

    // Create a test translator.
    tmgmt_translator_auto_create($this->container->get('plugin.manager.tmgmt.translator')->getDefinition('test_translator'));
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'test_node_type',
      'title' => 'English title',
      'translatable_text_field' => [
        'value' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
        'format' => 'html',
      ],
      'translatable_text_field_plain' => [
        'value' => 'plain text field value',
        'format' => 'plain_text',
      ],
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
    $expected = file_get_contents(\Drupal::service('extension.list.module')->getPath('oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html');
    $this->assertEquals($expected, $export);
  }

  /**
   * Tests that the formatter can import a correctly formatted Poetry html.
   */
  public function testHtmlFormatterImport() {
    /** @var \Drupal\oe_translation_poetry_html_formatter\PoetryHtmlFormatter $formatter */
    $formatter = $this->container->get('oe_translation_poetry.html_formatter');
    $actual_data = $formatter->import(\Drupal::service('extension.list.module')->getPath('oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html', TRUE);
    $expected_data = [
      1 => [
        'title' => [
          0 => [
            'value' => [
              '#text' => 'English title',
            ],
          ],
        ],
        'translatable_text_field' => [
          0 => [
            'value' => [
              '#text' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
            ],
          ],
        ],
        'translatable_text_field_plain' => [
          0 => [
            'value' => [
              '#text' => 'plain text field value',
            ],
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_data, $actual_data);
  }

}
