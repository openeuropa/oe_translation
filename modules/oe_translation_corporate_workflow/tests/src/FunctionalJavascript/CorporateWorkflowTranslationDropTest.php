<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_corporate_workflow\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_editorial\Traits\BatchTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;

/**
 * Tests the translation drop capability.
 *
 * When content is validated, there is a checkbox that allows to drop
 * the translations and not carry them over onto the next version.
 */
class CorporateWorkflowTranslationDropTest extends WebDriverTestBase {

  use BatchTrait;
  use TranslationsTestTrait;

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
    'tmgmt',
    'tmgmt_content',
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
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = \Drupal::entityTypeManager();

    $this->entityTypeManager->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
    \Drupal::service('oe_editorial_corporate_workflow.workflow_installer')->installWorkflow('page');
    $default_values = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];
    \Drupal::service('entity_version.entity_version_installer')->install('node', ['page'], $default_values);
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
   * Tests that translations can be dropped when validating content.
   */
  public function testTranslationRevisionsDropDefault(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a validated node directly and translate it.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $this->assertRevisions('node', 1);
    $this->assertRevisions('content_moderation_state', 1);

    $this->drupalGet($node->toUrl());

    // There should be no checkbox to drop languages as we don't have any
    // translations.
    $this->assertSession()->selectExists('Change to');
    $this->assertSession()->fieldNotExists('Do not carry over the translations');

    // Publish the content. We use the shortcuts for that.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    // We have 5 revisions all the way to published.
    $this->assertRevisions('node', 5);
    $this->assertRevisions('content_moderation_state', 5);

    // Translate the content.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // We still only have 5 revisions of each and only the published one has a
    // translation.
    $this->assertRevisions('node', 5, [5]);
    $this->assertRevisions('content_moderation_state', 5, [5]);

    // Assert the translation overview page shows the FR translation link.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkExistsExact('My node');
    $this->assertSession()->linkExistsExact('My node FR');
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'My node'],
      'fr' => ['title' => 'My node FR'],
    ]);

    // Create a new draft.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();

    // We now have 6 revisions, and the last two have translations as the
    // translation gets carried over from the published revision to the draft.
    $this->assertRevisions('node', 6, [5, 6]);
    $this->assertRevisions('content_moderation_state', 6, [5, 6]);

    // Go the node and validate the language dropping checkbox.
    $this->drupalGet($node->toUrl('latest-version'));
    $this->assertSession()->selectExists('Change to');
    $this->assertSession()->fieldValueEquals('Change to', 'needs_review');
    // Assert the checkbox is visible only when selecting validated or
    // published.
    $this->assertFalse($this->getSession()->getPage()->findField('Do not carry over the translations')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Change to', 'request_validation');
    $this->getSession()->wait(2);
    $this->assertFalse($this->getSession()->getPage()->findField('Do not carry over the translations')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Change to', 'validated');
    $this->getSession()->wait(2);
    $this->assertTrue($this->getSession()->getPage()->findField('Do not carry over the translations')->isVisible());
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->wait(2);
    $this->assertTrue($this->getSession()->getPage()->findField('Do not carry over the translations')->isVisible());

    // Moderate until we only have 1 step (this will create two revisions).
    $this->getSession()->getPage()->selectFieldOption('Change to', 'request_validation');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');
    $this->assertRevisions('node', 8, [5, 6, 7, 8]);
    $this->assertRevisions('content_moderation_state', 8, [5, 6, 7, 8]);

    // Validate and drop the translations.
    $this->drupalGet($node->toUrl('latest-version'));
    $this->assertSession()->fieldValueEquals('Change to', 'validated');
    $this->getSession()->getPage()->checkField('Do not carry over the translations');
    $this->getSession()->getPage()->pressButton('Apply');

    // We now have an extra revision but the validated one does not have the
    // translation.
    $this->assertRevisions('node', 9, [5, 6, 7, 8]);
    // Due to content moderation also handling the deletion of translations,
    // we lose the translation of the "request_validation" revision.
    // @see oe_translation_corporate_workflow_content_moderation_state_presave().
    $this->assertRevisions('content_moderation_state', 9, [5, 6, 7]);

    // Publish the content.
    $this->drupalGet($node->toUrl('latest-version'));
    $this->assertSession()->fieldValueEquals('Change to', 'published');
    // Once the content was validated, the checkbox is gone.
    $this->assertSession()->fieldNotExists('Do not carry over the translations');
    $this->getSession()->getPage()->pressButton('Apply');

    // One more revision but still no extra translations.
    $this->assertRevisions('node', 10, [5, 6, 7, 8]);
    $this->assertRevisions('content_moderation_state', 10, [5, 6, 7]);

    // Assert the translation overview page no longer shows the FR translation
    // link.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));

    $this->assertSession()->linkExistsExact('My node 2');
    $this->assertSession()->linkNotExistsExact('My node FR');
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'My node'],
    ]);
  }

  /**
   * Tests that translation dropping works also with the shortcuts.
   */
  public function testTranslationRevisionsDropWithShortcuts(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a validated node directly and translate it.
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $this->drupalGet($node->toUrl());

    // Publish the content. We use the shortcuts for that.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');

    $this->assertRevisions('node', 5);
    $this->assertRevisions('content_moderation_state', 5);

    // Translate the content.
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // We still only 5 revisions of each and only the published one has a
    // translation.
    $this->assertRevisions('node', 5, [5]);
    $this->assertRevisions('content_moderation_state', 5, [5]);

    // Create a new draft.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();

    // We now have 6 revisions, and the last two have translations as the
    // translation gets carried over from the published revision to the draft.
    $this->assertRevisions('node', 6, [5, 6]);
    $this->assertRevisions('content_moderation_state', 6, [5, 6]);

    // Go the node and publish it using the shortcuts.
    $this->drupalGet($node->toUrl('latest-version'));
    $this->getSession()->getPage()->selectFieldOption('Change to', 'published');
    $this->getSession()->getPage()->checkField('Do not carry over the translations');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->waitForBatchExecution();
    $this->assertSession()->waitForText('The moderation state has been updated.');
    $this->assertRevisions('node', 10, [5, 6, 7, 8]);
    // Due to content moderation also handling the deletion of translations,
    // we lose the translation of the "request_validation" revision.
    // @see oe_translation_corporate_workflow_content_moderation_state_presave().
    $this->assertRevisions('content_moderation_state', 10, [5, 6, 7]);
  }

  /**
   * Asserts the entity revisions are as we expect them.
   *
   * It assumes the node to have the ID of 1.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param int $count
   *   The number of revisions we expect.
   * @param array $with_translations
   *   The revision IDs we expect to have a FR translation.
   */
  protected function assertRevisions(string $entity_type, int $count, array $with_translations = []) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $storage->resetCache();
    $field = $entity_type === 'content_moderation_state' ? 'content_entity_id' : 'nid';
    $revision_ids = $storage->getQuery()->condition($field, 1)->allRevisions()->execute();
    $this->assertCount($count, $revision_ids);

    $revisions = $storage->loadMultipleRevisions(array_keys($revision_ids));
    foreach ($revisions as $revision) {
      if (in_array($revision->getRevisionId(), $with_translations)) {
        $this->assertTrue($revision->hasTranslation('fr'));
        continue;
      }
      $this->assertFalse($revision->hasTranslation('fr'));
    }
  }

}
