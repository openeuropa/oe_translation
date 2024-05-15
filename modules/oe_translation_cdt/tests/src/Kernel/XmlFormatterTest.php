<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use Drupal\Component\Datetime\Time;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\oe_translation_cdt\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_cdt\TranslationRequestCdt;
use Drupal\oe_translation_cdt\TranslationRequestCdtInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Drupal\Tests\oe_translation\Kernel\TranslationKernelTestBase;

/**
 * Tests the CDT XML content formatter.
 *
 * This is the service that transforms the translatable data into an XML file
 * to be sent with the CDT request for translation.
 *
 * @group batch1
 */
class XmlFormatterTest extends TranslationKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_cdt',
    'oe_translation_remote',
    'filter',
  ];

  /**
   * A test request.
   */
  protected TranslationRequestCdtInterface $request;

  /**
   * The XML content.
   */
  protected string $xml;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oe_translation_request');
    $this->installConfig(['filter']);
    $this->installConfig(['oe_translation_remote']);
    $this->installConfig(['oe_translation_cdt']);

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
    $this->request = TranslationRequestCdt::create([
      'bundle' => 'cdt',
      'source_language_code' => $node->language()->getId(),
      'target_languages' => [
        'langcode' => 'fr',
        'status' => TranslationRequestRemoteInterface::STATUS_LANGUAGE_REQUESTED,
      ],
      'translator_provider' => 'cdt',
      'request_status' => TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED,
    ]);

    $this->request->setContentEntity($node);
    $data = $this->container->get('oe_translation.translation_source_manager')->extractData($node->getUntranslated());
    $this->request->setData($data);
    $this->request->save();

    $this->xml = (string) file_get_contents(\Drupal::service('extension.path.resolver')->getPath('module', 'oe_translation_cdt') . '/tests/fixtures/cdt-source-file.xml');
    $this->xml = (string) str_replace('@transaction_identifier', (string) $this->request->id(), $this->xml);
    $this->xml = (string) str_replace('@drupal_version', \Drupal::VERSION, $this->xml);
    $this->xml = (string) str_replace('@module_version', (string) InstalledVersions::getPrettyVersion('openeuropa/oe_translation'), $this->xml);
    $this->xml = (string) str_replace('@node_id', (string) $this->request->getContentEntity()?->id(), $this->xml);
  }

  /**
   * Test the XML content formatter export.
   */
  public function testXmlContentExporter(): void {
    $time_prophecy = $this->prophesize(Time::class);
    $time_prophecy->getCurrentTime()->willReturn(1710935729);
    $this->container->set('datetime.time', $time_prophecy->reveal());

    $formatter = $this->container->get('oe_translation_cdt.xml_formatter');
    $export = $formatter->export($this->request);
    $this->assertEquals($this->xml, $export);
  }

  /**
   * Test the XML content formatter import.
   */
  public function testXmlContentImporter(): void {
    $formatter = $this->container->get('oe_translation_cdt.xml_formatter');
    assert($formatter instanceof ContentFormatterInterface);
    $translation_data = $formatter->import($this->xml, $this->request);
    $expected = [
      'title' => [
        [
          'value' => [
            '#text' => 'English title',
            '#translate' => TRUE,
            '#max_length' => 255,
            '#parent_label' => [
              0 => 'Title',
            ],
            '#translation' => [
              '#text' => 'English title',
            ],
          ],
        ],
      ],
      'ott_content_reference' => [
        [
          'entity' => [
            'title' => [
              [
                'value' => [
                  '#text' => 'Referenced node',
                  '#translate' => TRUE,
                  '#max_length' => 255,
                  '#parent_label' => [
                    0 => 'Content reference',
                    1 => 'Title',
                  ],
                  '#translation' => [
                    '#text' => 'Referenced node',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'translatable_text_field' => [
        [
          'value' => [
            '#text' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
            '#translate' => TRUE,
            '#max_length' => 255,
            '#format' => 'html',
            '#parent_label' => [
              0 => 'translatable_text_field',
            ],
            '#translation' => [
              '#text' => '<h1>This is a heading</h1><p>This is a paragraph</p>',
            ],
          ],
        ],
      ],
      'translatable_text_field_plain' => [
        [
          'value' => [
            '#text' => 'plain text field value',
            '#translate' => TRUE,
            '#max_length' => 255,
            '#format' => 'plain_text',
            '#parent_label' => [
              0 => 'translatable_text_field_plain',
            ],
            '#translation' => [
              '#text' => 'plain text field value',
            ],
          ],
        ],
      ],
    ];

    $this->assertEquals($expected, $translation_data);
  }

}
