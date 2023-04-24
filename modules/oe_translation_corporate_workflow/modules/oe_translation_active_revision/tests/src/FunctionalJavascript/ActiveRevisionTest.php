<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_active_revision\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_translation_active_revision\ActiveRevisionInterface;
use Drupal\oe_translation_active_revision\Entity\ActiveRevision;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Drupal\Tests\oe_editorial\Traits\BatchTrait;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;

/**
 * Tests Active revision functionality.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *
 * @group batch2
 */
class ActiveRevisionTest extends WebDriverTestBase {

  use BatchTrait;
  use TranslationsTestTrait;
  use CorporateWorkflowTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'toolbar',
    'content_translation',
    'user',
    'field',
    'text',
    'options',
    'oe_editorial_workflow_demo',
    'oe_translation',
    'oe_translation_corporate_workflow',
    'oe_translation_local',
    'oe_translation_active_revision',
    'oe_translation_active_revision_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('page');
    $default_values = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];
    \Drupal::service('entity_version.entity_version_installer')->install('node', ['page'], $default_values);

    \Drupal::entityTypeManager()->getStorage('entity_version_settings')->create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'page',
      'target_field' => 'version',
    ])->save();

    \Drupal::service('router.builder')->rebuild();

    $user = $this->setUpTranslatorUser();
    // Grant the editorial roles.
    foreach (['oe_author', 'oe_reviewer', 'oe_validator'] as $role) {
      $user->addRole($role);
      $user->save();
    }
    $this->drupalLogin($user);
  }

  /**
   * Tests that when we validated/publish, we can map the translations.
   */
  public function testActiveRevisionMapping(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a node, validate it, and translate it.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'field_non_translatable_field' => 'Non translatable value',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $node_storage->load($node->id());
    $node = $this->moderateNode($node, 'validated');

    $node->addTranslation('fr', ['title' => 'My FR node']);
    $node->save();

    // Go to the node moderation state form and assert we don't see our
    // elements for mapping the active revision.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->fieldNotExists('The new version needs NEW translations');
    $this->assertSession()->fieldNotExists('The new version does NOT need NEW translations (there have been only changes to non-translatable fields)');

    $node->delete();

    // Now create a node for each target moderation state and assert we will
    // get our form elements to choose what to do with the translations upon
    // making a new revision.
    $states = [
      'Validated',
      'Published',
    ];

    foreach ($states as $state) {
      // Create a node and translate it.
      /** @var \Drupal\node\NodeInterface $node */
      $node = $node_storage->create([
        'type' => 'page',
        'title' => 'My node',
        'field_non_translatable_field' => 'Non translatable value',
        'moderation_state' => 'draft',
      ]);
      $node->save();

      $node = $node_storage->load($node->id());
      $node = $this->moderateNode($node, 'published');

      $node->addTranslation('fr', ['title' => 'My FR node']);
      $node->save();

      // Keep track of the version 1 revision ID.
      $version_one_revision_id = $node->getRevisionId();

      $this->drupalGet($node->toUrl());
      $this->assertSession()->pageTextContains('My node');
      $this->assertSession()->pageTextContains('Non translatable value');

      $this->drupalGet('/fr/node/' . $node->id());
      $this->assertSession()->pageTextContains('My FR node');
      $this->assertSession()->pageTextContains('Non translatable value');

      // Start a new draft.
      $node->set('title', 'My updated node');
      $node->set('field_non_translatable_field', 'Non translatable updated value');
      $node->set('moderation_state', 'draft');
      $node->setNewRevision();
      $node->save();

      $this->drupalGet($node->toUrl('latest-version'));
      $this->assertSession()->fieldExists('The new version needs NEW translations');
      $this->assertSession()->fieldExists('The new version does NOT need NEW translations (there have been only changes to non-translatable fields)');
      $this->assertFalse($this->getSession()->getPage()->findField('The new version needs NEW translations')->isVisible());
      $this->getSession()->getPage()->selectFieldOption('Change to', $state);
      $this->assertTrue($this->getSession()->getPage()->findField('The new version needs NEW translations')->isVisible());
      $this->assertTrue($this->getSession()->getPage()->findField('The new version needs NEW translations')->isChecked());
      $this->assertTrue($this->getSession()->getPage()->findField('The new version does NOT need NEW translations (there have been only changes to non-translatable fields)')->isVisible());
      $this->assertFalse($this->getSession()->getPage()->findField('The new version does NOT need NEW translations (there have been only changes to non-translatable fields)')->isChecked());
      $this->assertTrue($this->getSession()->getPage()->findField('Keep current translations until new ones are synchronised.')->isVisible());
      $this->assertTrue($this->getSession()->getPage()->findField('Keep current translations until new ones are synchronised.')->isChecked());
      $this->assertTrue($this->getSession()->getPage()->findField('Delete current translations until new ones are synchronized')->isVisible());
      $this->assertFalse($this->getSession()->getPage()->findField('Delete current translations until new ones are synchronized')->isChecked());

      // Assert the state changes the other options.
      $this->getSession()->getPage()->findField('The new version does NOT need NEW translations (there have been only changes to non-translatable fields)')->click();
      $this->assertFalse($this->getSession()->getPage()->findField('Keep current translations until new ones are synchronised.')->isVisible());
      // Change back to the default.
      $this->getSession()->getPage()->findField('The new version needs NEW translations')->click();
      $this->assertTrue($this->getSession()->getPage()->findField('Keep current translations until new ones are synchronised.')->isVisible());
      $this->assertTrue($this->getSession()->getPage()->findField('Keep current translations until new ones are synchronised.')->isChecked());

      // Update the moderation state.
      $this->getSession()->getPage()->pressButton('Apply');
      $this->waitForBatchExecution();
      $this->assertSession()->waitForText('The moderation state has been updated.');

      // One active revision entity was created, mapping the FR language to the
      // previous revision.
      $this->assertCount(1, ActiveRevision::loadMultiple());
      $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
      $this->assertInstanceOf(ActiveRevisionInterface::class, $active_revision);
      $language_values = $active_revision->get('field_language_revision')->getValue();
      $this->assertCount(1, $language_values);
      $this->assertEquals([
        'entity_type' => 'node',
        'entity_id' => $node->id(),
        'entity_revision_id' => $version_one_revision_id,
        'langcode' => 'fr',
        'scope' => 0,
      ], $language_values[0]);

      // Visit the node page and assert we see the correct versions.
      if ($state === 'Published') {
        $this->drupalGet($node->toUrl());
      }
      else {
        $this->drupalGet($node->toUrl('latest-version'));
      }

      $this->assertSession()->pageTextContains('My updated node');
      $this->assertSession()->pageTextContains('Non translatable updated value');

      if ($state === 'Published') {
        $this->drupalGet('/fr/node/' . $node->id());
        $this->assertSession()->pageTextContains('My FR node');
        $this->assertSession()->pageTextContains('Non translatable value');
      }
      else {
        // We have 2 revision routes where our param converter applies.
        $this->drupalGet('/fr/node/' . $node->id() . '/latest');
        $this->assertSession()->pageTextContains('My FR node');
        $this->assertSession()->pageTextContains('Non translatable value');
        $this->drupalGet('/fr/node/' . $node->id() . '/revisions/' . $version_one_revision_id . '/view');
        $this->assertSession()->pageTextContains('My FR node');
        $this->assertSession()->pageTextContains('Non translatable value');
      }

      // Visit a view of nodes and assert that also in the teaser, we see the
      // correct version. This can only work for the Published case.
      if ($state === 'Published') {
        $this->drupalGet('/nodes');
        $this->assertSession()->pageTextContains('My updated node');
        $this->assertSession()->pageTextContains('Non translatable updated value');
        $this->drupalGet('/fr/nodes');
        $this->assertSession()->pageTextContains('My FR node');
        $this->assertSession()->pageTextContains('Non translatable value');
      }

      // Delete the active revision and assert we see the revisions as we would
      // by default.
      $active_revision->delete();

      if ($state === 'Published') {
        $this->drupalGet($node->toUrl());
      }
      else {
        $this->drupalGet($node->toUrl('latest-version'));
      }

      $this->assertSession()->pageTextContains('My updated node');
      $this->assertSession()->pageTextContains('Non translatable updated value');
      if ($state === 'Published') {
        $this->drupalGet('/fr/node/' . $node->id());
      }
      else {
        $this->drupalGet('/fr/node/' . $node->id() . '/latest');
      }
      $this->assertSession()->pageTextContains('My FR node');
      $this->assertSession()->pageTextContains('Non translatable updated value');

      if ($state === 'Published') {
        $this->drupalGet('/nodes');
        $this->assertSession()->pageTextContains('My updated node');
        $this->assertSession()->pageTextContains('Non translatable updated value');
        $this->drupalGet('/fr/nodes');
        $this->assertSession()->pageTextContains('My FR node');
        $this->assertSession()->pageTextContains('Non translatable updated value');
      }

      $node->delete();
    }

  }

  /**
   * Tests that new translations are mapped to the correct revision.
   *
   * Tests the situation in which we have for version 1 a translation in FR and
   * then we make version 2 for which we map FR to version 1. However, in
   * version 2 we add a translation in IT as well. Then, in version 3, we keep
   * the mapping of FR to version 1, but the IT to version 2.
   */
  public function testIncrementingVersionTranslations(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create version 1 with a FR translation.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My version 1 node',
      'field_non_translatable_field' => 'Non translatable value',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $node_storage->load($node->id());
    $node = $this->moderateNode($node, 'published');

    $node->addTranslation('fr', ['title' => 'My FR version 1 node']);
    $node->save();

    // Keep track of the version 1 revision ID.
    $version_one_revision_id = $node->getRevisionId();

    // Make a change and create version 2, mapping FR to version 1.
    $node->set('title', 'My version 2 node');
    $node->set('field_non_translatable_field', 'Non translatable updated version 2 value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    // By default, the mapping option is selected by default.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // One active revision entity was created, mapping the FR language to the
    // previous revision.
    $this->assertCount(1, ActiveRevision::loadMultiple());
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $this->assertInstanceOf(ActiveRevisionInterface::class, $active_revision);
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertCount(1, $language_values);
    $this->assertEquals([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'entity_revision_id' => $version_one_revision_id,
      'langcode' => 'fr',
      'scope' => 0,
    ], $language_values[0]);

    $node_storage->resetCache();
    $node = $node_storage->load($node->id());

    // Add the IT translation to version 2.
    $node->addTranslation('it', ['title' => 'My IT version 2 node']);
    $node->save();

    // Keep track of the version 2 revision ID.
    $version_two_revision_id = $node->getRevisionId();

    // Make a change and create version 3, mapping IT to version 2 and keeping
    // FR mapped to version 1.
    $node->set('title', 'My version 3 node');
    $node->set('field_non_translatable_field', 'Non translatable version 3 updated value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    // By default, the mapping option is selected by default.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    $this->assertCount(1, ActiveRevision::loadMultiple());
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $this->assertInstanceOf(ActiveRevisionInterface::class, $active_revision);
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertCount(2, $language_values);
    $this->assertEquals([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'entity_revision_id' => $version_one_revision_id,
      'langcode' => 'fr',
      'scope' => 0,
    ], $language_values[0]);
    $this->assertEquals([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'entity_revision_id' => $version_two_revision_id,
      'langcode' => 'it',
      'scope' => 0,
    ], $language_values[1]);

    $this->drupalGet('/fr/node/' . $node->id());
    $this->assertSession()->pageTextContains('My FR version 1 node');
    $this->assertSession()->pageTextContains('Non translatable value');
    $this->drupalGet('/it/node/' . $node->id());
    $this->assertSession()->pageTextContains('My IT version 2 node');
    // Since we added the IT translation in version 2, we see the non
    // translatable value from that version.
    $this->assertSession()->pageTextContains('Non translatable updated version 2 value');

    // Delete the node and assert the active revision entity is also deleted.
    $node->delete();
    $this->assertCount(0, ActiveRevision::loadMultiple());
  }

  /**
   * Tests that the active revision applies in the correct scope.
   *
   * We ensure that if the scope is set to only apply to published revisions,
   * the validated one no longer shows the mapping.
   */
  public function testActiveRevisionScope(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create version 1 with a FR translation.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My version 1 node',
      'field_non_translatable_field' => 'Non translatable value',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $node_storage->load($node->id());
    $node = $this->moderateNode($node, 'published');

    $node->addTranslation('fr', ['title' => 'My FR version 1 node']);
    $node->save();

    // Keep track of the version 1 revision ID.
    $version_one_revision_id = $node->getRevisionId();

    // Make a change and create version 2, mapping FR to version 1.
    $node->set('title', 'My version 2 node');
    $node->set('field_non_translatable_field', 'Non translatable updated version 2 value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    // By default, the mapping option is selected by default.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // Make a change and create version 3 but only validated.
    $node->set('title', 'My version 3 node');
    $node->set('field_non_translatable_field', 'Non translatable version 3 updated value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    // By default, the mapping option is selected by default.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Validated');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    $this->assertCount(1, ActiveRevision::loadMultiple());
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $this->assertInstanceOf(ActiveRevisionInterface::class, $active_revision);
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertCount(1, $language_values);
    $this->assertEquals([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'entity_revision_id' => $version_one_revision_id,
      'langcode' => 'fr',
      'scope' => 0,
    ], $language_values[0]);

    // For the moment, both the published version and the validated version
    // show the version 1 translation in FR.
    $this->drupalGet('/fr/node/' . $node->id());
    $this->assertSession()->pageTextContains('My FR version 1 node');
    $this->assertSession()->pageTextContains('Non translatable value');
    $this->drupalGet('/fr/node/' . $node->id() . '/latest');
    $this->assertSession()->pageTextContains('My FR version 1 node');
    $this->assertSession()->pageTextContains('Non translatable value');

    // Now change the scope of the mapping to only apply to the Published
    // version.
    $language_values[0]['scope'] = LanguageWithEntityRevisionItem::SCOPE_PUBLISHED;
    $active_revision->set('field_language_revision', $language_values);
    $active_revision->save();

    $this->drupalGet('/fr/node/' . $node->id());
    $this->assertSession()->pageTextContains('My FR version 1 node');
    $this->assertSession()->pageTextContains('Non translatable value');
    $this->drupalGet('/fr/node/' . $node->id() . '/latest');
    $this->assertSession()->pageTextContains('My FR version 1 node');
    $this->assertSession()->pageTextContains('Non translatable version 3 updated value');

    // Publish the node and make a new version, validating it.
    $this->drupalGet('/node/' . $node->id() . '/latest');
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');
    $node->set('title', 'My version 4 node');
    $node->set('field_non_translatable_field', 'Non translatable updated version 4 value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    // By default, the mapping option is selected by default.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Validated');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // Assert that the active revision mapping scope is back to support both
    // moderation states.
    $active_revision = \Drupal::entityTypeManager()->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity('node', $node->id());
    $language_values = $active_revision->get('field_language_revision')->getValue();
    $this->assertEquals([
      'entity_type' => 'node',
      'entity_id' => $node->id(),
      'entity_revision_id' => $version_one_revision_id,
      'langcode' => 'fr',
      'scope' => 0,
    ], $language_values[0]);
  }

  /**
   * Tests the translation delete.
   *
   * Tests the with the override on the moderation form from this module, we
   * can still drop the translations. This is also tested in
   * CorporateWorkflowTranslationDropTest.
   */
  public function testTranslationsDrop(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create version 1 with a FR translation.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My version 1 node',
      'field_non_translatable_field' => 'Non translatable value',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $node = $node_storage->load($node->id());
    $node = $this->moderateNode($node, 'published');

    $node->addTranslation('fr', ['title' => 'My FR version 1 node']);
    $node->save();

    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertTrue($node->hasTranslation('fr'));

    $version_one_revision_id = $node->getRevisionId();

    // Make a change and create version 2, marking to delete the translations.
    $node->set('title', 'My version 2 node');
    $node->set('field_non_translatable_field', 'Non translatable updated version 2 value');
    $node->set('moderation_state', 'draft');
    $node->setNewRevision();
    $node->save();
    $this->drupalGet($node->toUrl('latest-version'));
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->findField('Delete current translations until new ones are synchronized')->click();
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // Assert that version 1 has the translation but version 2 no longer does.
    $version_one = $node_storage->loadRevision($version_one_revision_id);
    $this->assertTrue($version_one->hasTranslation('fr'));
    $node = $node_storage->load($node->id());
    $this->assertFalse($node->hasTranslation('fr'));
  }

}
