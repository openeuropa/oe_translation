<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_active_revision_link_lists\FunctionalJavascript;

use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\Tests\oe_translation_active_revision\FunctionalJavascript\ActiveRevisionTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests active revision functionality with link lists.
 *
 * @group batch1
 */
class ActiveRevisionLinkListTest extends ActiveRevisionTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_active_revision_link_lists_test',
    'oe_link_lists_internal_source',
    'oe_link_lists_manual_source',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $role = Role::load('oe_translator');
    $role->grantPermission('view link list');
    $role->grantPermission('access link list canonical page');
    $role->save();

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a node, publish it, and translate it.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My version 1 node',
      'field_non_translatable_field' => 'Non translatable v1 value',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $node_storage->load($node->id());
    $node = $this->moderateNode($node, 'published');

    $translation = $node->addTranslation('fr', ['title' => 'My version 1 FR node']);
    $translation->save();

    // Start a new draft and publish it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My version 2 node');
    $node->set('field_non_translatable_field', 'Non translatable v2 value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // Quick assertion that we have a mapping.
    $this->drupalGet('/node/' . $node->id() . '/translations');

    $table = $this->getSession()->getPage()->find('css', 'table.existing-translations-table');
    $french_row = $table->find('xpath', '//tr[@hreflang="fr"]');
    $this->assertEquals('Mapped to version 1.0.0', $french_row->find('xpath', '//td[2]')->getText());
  }

  /**
   * Tests that internal source links are showing the correct translation.
   */
  public function testActiveRevisionWithInternalSource(): void {
    // Create a dynamic link list that shows Pages.
    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'My link list admin',
      'status' => 1,
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'internal',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'page',
        ],
      ],
      'display' => [
        'plugin' => 'oe_translation_active_revision_link_lists_test_teaser',
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalGet($link_list->toUrl());

    $this->assertSession()->linkExistsExact('My version 2 node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');

    // Switch to FR and assert we see the mapped version.
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 1 FR node');
    $this->assertSession()->pageTextContains('Non translatable v1 value');

    // Map the active revision to NULL and assert we now see the EN version 2
    // when going to FR.
    $node = $this->drupalGetNodeByTitle('My version 2 node');
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertCount(1, $language_values);
    $language_values[0]['entity_revision_id'] = 0;
    $active_revision->set('field_language_revision', $language_values);
    $active_revision->save();
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 2 node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');

    // Remove the mapping completely and assert the Drupal fallback.
    $active_revision->delete();
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 1 FR node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');
  }

  /**
   * Tests that manual source links are showing the correct translation.
   */
  public function testActiveRevisionWithManualSource(): void {
    $node = $this->drupalGetNodeByTitle('My version 2 node');

    // Create a manual link list that shows our page.
    $internal_link = \Drupal::entityTypeManager()->getStorage('link_list_link')->create([
      'bundle' => 'internal',
      'target' => $node->id(),
      'status' => 1,
    ]);
    $internal_link->save();

    $link_list = LinkList::create([
      'bundle' => 'manual',
      'title' => 'My link list',
      'administrative_title' => 'My link list admin',
      'links' => [$internal_link],
      'status' => 1,
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'manual_links',
        'plugin_configuration' => [
          'links' => [
            [
              'entity_id' => $internal_link->id(),
              'entity_revision_id' => $internal_link->getRevisionId(),
            ],
          ],
        ],
      ],
      'display' => [
        'plugin' => 'oe_translation_active_revision_link_lists_test_teaser',
      ],
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalGet($link_list->toUrl());
    $this->assertSession()->linkExistsExact('My version 2 node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');

    // Switch to FR and assert we see the mapped version.
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 1 FR node');
    $this->assertSession()->pageTextContains('Non translatable v1 value');

    // Map the active revision to NULL and assert we now see the EN version 2
    // when going to FR.
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertCount(1, $language_values);
    $language_values[0]['entity_revision_id'] = 0;
    $active_revision->set('field_language_revision', $language_values);
    $active_revision->save();
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 2 node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');

    // Remove the mapping completely and assert the Drupal fallback.
    $active_revision->delete();
    $this->drupalGet('/fr/link_list/' . $link_list->id());
    $this->assertSession()->linkExistsExact('My version 1 FR node');
    $this->assertSession()->pageTextContains('Non translatable v2 value');
  }

}
