<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the local translation capability.
 */
class LocalTranslationTest extends TranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'address',
    'paragraphs',
    'entity_reference_revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Mark the paragraph bundles translatable.
    \Drupal::service('content_translation.manager')->setEnabled('paragraph', 'demo_paragraph_type', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('paragraph', 'demo_inner_paragraph_type', TRUE);

    // Mark the test entity reference field as embeddable for TMGMT to behave
    // as composite entities.
    $this->config('tmgmt_content.settings')
      ->set('embedded_fields', [
        'node' => [
          'ott_content_reference' => TRUE,
        ],
      ])
      ->save();
  }

  /**
   * Tests the translation field labels and default values.
   */
  public function testLocalTranslationFields(): void {
    // Create a node that we can reference.
    /** @var \Drupal\node\NodeInterface $node */
    $referenced_node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Referenced node',
    ]);
    $referenced_node->save();

    // Create a node to be translated.
    $inner_paragraph_one = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'inner field value 1',
    ]);
    $inner_paragraph_one->save();

    $inner_paragraph_two = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'inner field value 2',
    ]);
    $inner_paragraph_two->save();

    $top_paragraph_one = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $inner_paragraph_one->id(),
          'target_revision_id' => $inner_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $inner_paragraph_two->id(),
          'target_revision_id' => $inner_paragraph_two->getRevisionId(),
        ],
      ],
    ]);
    $top_paragraph_one->save();

    $top_paragraph_two = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 2',
    ]);
    $top_paragraph_two->save();

    $node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Translation node',
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
      'ott_address' => [
        'country_code' => 'BE',
        'given_name' => 'The first name',
        'family_name' => 'The last name',
        'locality' => 'Brussels',
        'postal_code' => '1000',
        'address_line1' => 'The street name',
      ],
      'ott_content_reference' => $referenced_node->id(),
    ]);

    $node->save();

    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));

    // Prepare the test data for each of the fields.
    $fields = [];
    $fields['title'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Title']",
      'value' => 'Translation node',
    ];
    $fields['address_country_code'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - The two-letter country code.']",
      'value' => 'BE',
    ];
    $fields['address_locality'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - City']",
      'value' => 'Brussels',
    ];
    $fields['address_postal_code'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - Postal code']",
      'value' => 1000,
    ];
    $fields['address_address_line1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - Street address']",
      'value' => 'The street name',
    ];
    $fields['address_given_name'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - First name']",
      'value' => 'The first name',
    ];
    $fields['address_family_name'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - Last name']",
      'value' => 'The last name',
    ];
    $fields['ott_content_reference'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Content reference - Title']",
      'value' => 'Referenced node',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (0) - Inner paragraph field']",
      'value' => 'inner field value 1',
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (1) - Inner paragraph field']",
      'value' => 'inner field value 2',
    ];
    $fields['ott_top_leve_paragraphs__0__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Top level paragraph field']",
      'value' => 'top field value 1',
    ];
    $fields['ott_top_leve_paragraphs__1__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (1) - Top level paragraph field']",
      'value' => 'top field value 2',
    ];

    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      if (!$table_header) {
        $this->fail(sprintf('The form label for the "%s" field was not found on the page.', $key));
      }

      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");
      if (!$element) {
        $this->fail(sprintf('The translation element for the "%s" field was not found on the page.', $key));
      }

      $this->assertEquals($data['value'], $element->getText());
    }
  }

}
