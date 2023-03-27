<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_epoetry\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetry;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the ePoetry HTML content formatter.
 *
 * This is the service that transforms the translatable data into an HTML file
 * to be sent with the ePoetry request for translation.
 *
 * @group batch1
 */
class HtmlFormatterTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_epoetry',
    'oe_translation_remote',
    'filter',
  ];

  /**
   * A test request.
   *
   * @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['filter']);
    $this->installConfig(['oe_translation_remote']);
    $this->installConfig(['oe_translation_epoetry']);

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
      'bundle' => 'oe_demo_translatable_page',
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
      'bundle' => 'oe_demo_translatable_page',
    ];
    $field = FieldConfig::create($field_definition);
    $field->save();

    // Mark the test entity reference field as embeddable.
    $this->config('oe_translation.settings')
      ->set('translation_source_embedded_fields', [
        'node' => [
          'ott_content_reference' => TRUE,
        ],
      ])
      ->save();

    // Create a format for the content.
    FilterFormat::create([
      'format' => 'html',
      'name' => 'Html',
      'weight' => 1,
      'filters' => [],
    ])->save();

    // Create a node to be referenced.
    $referenced_node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Referenced node',
    ]);
    $referenced_node->save();

    // Create a node to format.
    /** @var \Drupal\node\NodeInterface $node */
    $node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'English title',
      'ott_content_reference' => $referenced_node->id(),
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

    // Create a translation request with the node data.
    /** @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request */
    $this->request = TranslationRequestEpoetry::create([
      'bundle' => 'epoetry',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => [
        'langcode' => 'fr',
        'status' => 'Requested',
      ],
      'translator_provider' => 'epoetry',
      'request_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED,
    ]);

    $this->request->setContentEntity($node);
    $data = $this->container->get('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $this->request->setData($data);
    $this->request->save();
  }

  /**
   * Test the HTML content formatter.
   */
  public function testHtmlContentExporter(): void {
    /** @var \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $formatter */
    $formatter = $this->container->get('oe_translation_epoetry.html_formatter');

    /** @var \Drupal\Core\Render\Markup $export */
    $export = $formatter->export($this->request);
    $expected = file_get_contents(\Drupal::service('extension.path.resolver')->getPath('module', 'oe_translation_epoetry') . '/tests/fixtures/formatted-content-original.html');
    $expected = str_replace('@request_id', $this->request->id(), $expected);
    $this->assertEquals($expected, $export);
  }

  /**
   * Test the HTML content formatter.
   */
  public function testHtmlContentImporter(): void {
    /** @var \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $formatter */
    $formatter = $this->container->get('oe_translation_epoetry.html_formatter');

    $formatted_content = file_get_contents(\Drupal::service('extension.path.resolver')->getPath('module', 'oe_translation_epoetry') . '/tests/fixtures/formatted-content-translated.html');

    $actual_data = $formatter->import($formatted_content, $this->request);

    $expected_data = [
      1 => [
        'title' => [
          0 => [
            'value' => [
              '#text' => 'English title',
              '#translate' => TRUE,
              '#max_length' => 255,
              '#parent_label' => [
                0 => 'Title',
              ],
              '#translation' => [
                '#text' => 'French title',
              ],
            ],
          ],
        ],
        'ott_content_reference' => [
          0 => [
            'entity' => [
              'title' => [
                0 => [
                  'value' => [
                    '#text' => 'Referenced node',
                    '#translate' => TRUE,
                    '#max_length' => 255,
                    '#parent_label' => [
                      0 => 'Content reference',
                      1 => 'Title',
                    ],
                    '#translation' => [
                      '#text' => 'Referenced node in French',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
        'translatable_text_field' => [
          0 => [
            'value' => [
              '#text' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
              '#translate' => TRUE,
              '#max_length' => 255,
              '#format' => 'html',
              '#parent_label' => [
                0 => 'translatable_text_field',
              ],
              '#translation' => [
                '#text' => '<h1>This is a FR heading</h1><p>This is a FR paragraph</p>',
              ],
            ],
          ],
        ],
        'translatable_text_field_plain' => [
          0 => [
            'value' => [
              '#text' => 'plain text field value',
              '#translate' => TRUE,
              '#max_length' => 255,
              '#format' => 'plain_text',
              '#parent_label' => [
                0 => 'translatable_text_field_plain',
              ],
              '#translation' => [
                '#text' => 'plain text FR field value',
              ],
            ],
          ],
        ],
      ],
    ];

    $this->assertEquals($expected_data, $actual_data);
  }

}
