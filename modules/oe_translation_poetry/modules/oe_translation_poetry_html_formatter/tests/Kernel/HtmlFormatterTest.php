<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry_html_formatter\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the Poetry Html Formatter.
 */
class HtmlFormatterTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'field',
    'options',
    'text',
    'node',
    'user',
    'system',
    'filter',
    'language',
    'oe_multilingual',
    'content_translation',
    'views',
    'tmgmt',
    'tmgmt_content',
    'tmgmt_test',
    'oe_translation_poetry_html_formatter',
  ];

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
  public function setUp() {
    parent::setUp();

    $this->installConfig([
      'system',
      'node',
      'field',
      'user',
      'language',
      'oe_multilingual',
    ]);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('tmgmt_job');
    $this->installEntitySchema('tmgmt_job_item');

    $this->installSchema('node', 'node_access');
    $this->installSchema('system', 'sequences');

    $node_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $node_type->save();

    tmgmt_translator_auto_create(\Drupal::service('plugin.manager.tmgmt.translator')->getDefinition('test_translator'));
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create([
      'type' => 'test_node_type',
      'title' => 'English title',
    ]);
    $node->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $this->job = $job;
    $job_item = tmgmt_job_item_create('content', $node->getEntityTypeId(), $node->id(), ['tjid' => $job->id()]);
    $job_item->save();
    $this->jobItem = $job_item;
  }

  /**
   * Tests that the formatter exports a correctly formatter Poetry html.
   */
  public function testHtmlFormatterExport() {
    /** @var \Drupal\oe_translation_poetry_html_formatter\PoetryHtmlFormatter $formatter */
    $formatter = $this->container->get('oe_translation_poetry.html_formatter');

    /** @var \Drupal\Core\Render\Markup $export */
    $export = $formatter->export($this->job);
    $expected = file_get_contents(drupal_get_path('module', 'oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html');
    $this->assertEqual($expected, $export);
  }

  /**
   * Tests that the formatter can import a correctly formatter Poetry html.
   */
  public function testHtmlFormatterImport() {
    /** @var \Drupal\oe_translation_poetry_html_formatter\PoetryHtmlFormatter $formatter */
    $formatter = $this->container->get('oe_translation_poetry.html_formatter');
    $processed_data = $formatter->import(drupal_get_path('module', 'oe_translation_poetry_html_formatter') . '/tests/fixtures/formatted-content.html', TRUE);

    // Let's get the data from the job_item and remove all the information that
    // doesn't get send to DGT.
    $data = \Drupal::service('tmgmt.data')->filterTranslatable($this->job_item->getData());
    $unflattened_data = \Drupal::service('tmgmt.data')->unflatten($data);
    unset($unflattened_data["title"][0]["value"]["#translate"]);
    unset($unflattened_data["title"][0]["value"]["#max_length"]);
    unset($unflattened_data["title"][0]["value"]["#status"]);
    unset($unflattened_data["title"][0]["value"]["#parent_label"]);
    $expected_data = [$this->job_item->id() => $unflattened_data];
    $this->assertEqual($processed_data, $expected_data);
  }

}
