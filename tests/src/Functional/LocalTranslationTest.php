<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation\Functional;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
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
    'block_content',
    'oe_translation_metatag_test',
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
   * Tests that we can make local translations with Block entities.
   */
  public function testBlockTranslations(): void {
    // Enable the "permission" translator on the block entity.
    \Drupal::state()->set('oe_translation_test_enabled_translators', ['block_content']);

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'basic',
    ]);
    $bundle->save();

    \Drupal::service('content_translation.manager')->setEnabled('block_content', 'basic', TRUE);

    // Create a custom block.
    $custom_block = BlockContent::create([
      'type' => 'basic',
      'info' => 'Custom Block',
      'langcode' => 'en',
    ]);
    $custom_block->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_translator');
    $permissions = $role->getPermissions();
    $permissions[] = 'administer menu';
    $permissions[] = 'administer blocks';
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    $this->drupalGet($custom_block->toUrl('drupal:content-translation-overview'));

    // Translate in BG.
    $this->getSession()->getPage()->find('css', 'li.tmgmttranslate-localadd a[hreflang="bg"]')->click();
    $this->getSession()->getPage()->find('css', '#edit-info0value-translation')->setValue('BG translation');
    $this->getSession()->getPage()->pressButton('Save and complete translation');
    $this->assertSession()->pageTextContainsOnce('The translation for Custom Block has been saved as completed.');
    $this->assertSession()->linkExistsExact('BG translation');

    // Update the BG translation.
    $this->getSession()->getPage()->find('css', 'li.tmgmttranslate-localadd a[hreflang="bg"]')->click();
    $translation_field = $this->getSession()->getPage()->find('css', '#edit-info0value-translation');
    $this->assertEquals('BG translation', $translation_field->getValue());
    $translation_field->setValue('Updated BG translation');
    $this->getSession()->getPage()->pressButton('Save and complete translation');
    $this->assertSession()->pageTextContainsOnce('The translation for Custom Block has been saved as completed.');
    $this->assertSession()->linkExistsExact('Updated BG translation');
  }

  /**
   * Tests the translation field labels and default values.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testLocalTranslationFields(): void {
    // Create a node that we can reference.
    /** @var \Drupal\node\NodeInterface $node */
    $referenced_node = Node::create([
      'type' => 'oe_demo_translatable_page',
      'title' => 'Referenced node',
    ]);
    $referenced_node->save();

    // Create paragraphs to reference and translate.
    $grandchild_paragraph_one = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'grandchild field value 1',
    ]);
    $grandchild_paragraph_one->save();

    $grandchild_paragraph_two = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'grandchild field value 2',
    ]);
    $grandchild_paragraph_two->save();

    $child_paragraph_one = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'child field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $grandchild_paragraph_one->id(),
          'target_revision_id' => $grandchild_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $grandchild_paragraph_two->id(),
          'target_revision_id' => $grandchild_paragraph_two->getRevisionId(),
        ],
      ],
    ]);
    $child_paragraph_one->save();

    $child_paragraph_two = Paragraph::create([
      'type' => 'demo_inner_paragraph_type',
      'ott_inner_paragraph_field' => 'child field value 2',
    ]);
    $child_paragraph_two->save();

    $top_paragraph_one = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 1',
      'ott_inner_paragraphs' => [
        [
          'target_id' => $child_paragraph_one->id(),
          'target_revision_id' => $child_paragraph_one->getRevisionId(),
        ],
        [
          'target_id' => $child_paragraph_two->id(),
          'target_revision_id' => $child_paragraph_two->getRevisionId(),
        ],
      ],
    ]);
    $top_paragraph_one->save();

    $top_paragraph_two = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 2',
    ]);
    $top_paragraph_two->save();

    // Create a node to be translated.
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
      'field_metatag' => serialize([
        'description' => 'description override',
        'abstract' => 'abstract override',
        'geo_placename' => 'geo place override',
      ]),
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
      'translate' => TRUE,
    ];
    $fields['address_country_code'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Address - The two-letter country code']",
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
    $fields['field_metatag_basic_description'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Metatag - Basic tags - Description']",
      'value' => 'description override',
    ];
    $fields['field_metatag_basic_abstract'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Metatag - Basic tags - Abstract']",
      'value' => 'abstract override',
    ];
    $fields['field_metatag_advanced_abstract'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Metatag - Advanced - Geographical place name']",
      'value' => 'geo place override',
    ];
    $fields['ott_content_reference'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Content reference - Title']",
      'value' => 'Referenced node',
      'translate' => TRUE,
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (0) - Demo inner paragraph type (0) - Inner paragraph field']",
      'value' => 'grandchild field value 1',
      'translate' => TRUE,
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (0) - Demo inner paragraph type (1) - Inner paragraph field']",
      'value' => 'grandchild field value 2',
      'translate' => TRUE,
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (0) - Inner paragraph field']",
      'value' => 'child field value 1',
      'translate' => TRUE,
    ];
    $fields['ott_inner_paragraphs__0__ott_inner_paragraph_ott__1'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Demo inner paragraph type (1) - Inner paragraph field']",
      'value' => 'child field value 2',
      'translate' => TRUE,
    ];
    $fields['ott_top_level_paragraphs__0__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (0) - Top level paragraph field']",
      'value' => 'top field value 1',
      'translate' => TRUE,
    ];
    $fields['ott_top_level_paragraphs__1__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type (1) - Top level paragraph field']",
      'value' => 'top field value 2',
      'translate' => TRUE,
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
      // Set a translation value.
      if (isset($data['translate'])) {
        $element->setValue($data['value'] . ' FR');
      }
    }

    // Save the translation.
    $this->getSession()->getPage()->pressButton('Save and complete translation');

    // Start a new local translation and assert that the default values are now
    // the translated values from the previous translation.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));

    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");

      $expected_value = isset($data['translate']) ? $data['value'] . ' FR' : $data['value'];
      $this->assertEquals($expected_value, $element->getText());
    }

    // Reorder top level paragraphs.
    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'Translation node']);
    $node = reset($node);

    $field_values = $node->get('ott_top_level_paragraphs')->getValue();
    $new_values = [];
    $new_values[0] = $field_values[1];
    $new_values[1] = $field_values[0];
    $node->set('ott_top_level_paragraphs', $new_values);
    $node->setNewRevision(TRUE);
    $node->save();

    // Assert that when we navigate back to the local translation, the default
    // values are still inline with the revision where we started from.
    // Remember that we didn't save the translation task.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->getSession()->getPage()->clickLink('Edit local translation');

    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");

      $expected_value = isset($data['translate']) ? $data['value'] . ' FR' : $data['value'];
      $this->assertEquals($expected_value, $element->getText());
    }

    // Make changes to the paragraphs and remove some of them.
    $top_paragraph_three = Paragraph::create([
      'type' => 'demo_paragraph_type',
      'ott_top_level_paragraph_field' => 'top field value 3',
    ]);
    $top_paragraph_three->save();

    $node->set('ott_top_level_paragraphs', [$top_paragraph_three]);
    $node->setNewRevision(TRUE);
    $node->save();

    // Go back to the translation form. Remember that we didn't save the
    // translation task.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->getSession()->getPage()->clickLink('Edit local translation');

    // Run the same assertions.
    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");

      $expected_value = isset($data['translate']) ? $data['value'] . ' FR' : $data['value'];
      $this->assertEquals($expected_value, $element->getText());
    }

    // Save the task.
    $this->getSession()->getPage()->pressButton('Save and complete translation');

    // Start a new translation and assert that now we have the changed default
    // values.
    $this->drupalGet(Url::fromRoute('oe_translation.permission_translator.create_local_task', [
      'entity' => $node->id(),
      'source' => 'en',
      'target' => 'fr',
      'entity_type' => 'node',
    ]));

    // Some fields were removed.
    $removed = [
      'ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__0',
      'ott_inner_paragraphs__0__ott_inner_paragraphs__0__ott_inner_paragraph_ott__1',
      'ott_inner_paragraphs__0__ott_inner_paragraph_ott__0',
      'ott_inner_paragraphs__0__ott_inner_paragraph_ott__1',
      'ott_top_level_paragraphs__1__ott_top_level_paragraph_ott__0',
    ];

    foreach ($removed as $name) {
      unset($fields[$name]);
    }

    // Only one paragraph remained so the delta was removed.
    $fields['ott_top_level_paragraphs__0__ott_top_level_paragraph_ott__0'] = [
      'xpath' => "//table//th[normalize-space(text()) = 'Demo paragraph type - Top level paragraph field']",
      'value' => 'top field value 3',
      // This one doesn't have a previous translation.
    ];

    foreach ($fields as $key => $data) {
      $table_header = $this->getSession()->getPage()->find('xpath', $data['xpath']);
      $table = $table_header->getParent()->getParent()->getParent();
      $element = $table->find('xpath', "//textarea[contains(@name,'[translation]')]");

      $expected_value = isset($data['translate']) ? $data['value'] . ' FR' : $data['value'];
      $this->assertEquals($expected_value, $element->getText());
    }
  }

}
