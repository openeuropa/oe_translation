<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_corporate_workflow\Functional;

use Drupal\content_moderation\Entity\ContentModerationState;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\oe_translation\Entity\TranslationRequest;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_local\TranslationRequestLocal;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\user\Entity\Role;

/**
 * Tests the local translation revision capability.
 *
 * Using the corporate workflow, translations can only be started from the
 * validated state, i.e. when a new major version is created.
 *
 * Moreover, translations need to be saved onto the latest revision of the
 * entity's major version. In other words, if the translation is started when
 * the entity is in validated state (the minimum), and the entity gets published
 * before the translation comes back, the latter should be saved on the
 * published revision. But not on any future drafts which create new minor
 * versions.
 *
 * @group batch1
 */
class CorporateWorkflowTranslationTest extends BrowserTestBase {

  use CorporateWorkflowTrait;
  use CorporateWorkflowTranslationTrait;
  use TranslationsTestTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

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
    'paragraphs',
    'block',
    'oe_editorial_workflow_demo',
    'oe_translation',
    'oe_translation_local',
    'oe_translation_corporate_workflow',
    'oe_translation_corporate_workflow_test',
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

    $this->entityTypeManager = \Drupal::service('entity_type.manager');

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
    // We apply the entity version setting for the version field.
    $this->entityTypeManager->getStorage('entity_version_settings')->create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'page',
      'target_field' => 'version',
    ])->save();
    \Drupal::service('router.builder')->rebuild();

    $form_display = EntityFormDisplay::load('node.oe_workflow_demo.default');
    $form_display->setComponent('field_workflow_paragraphs', [
      'type' => 'entity_reference_paragraphs',
      'settings' => [
        'title' => 'Paragraph',
        'title_plural' => 'Paragraphs',
        'edit_mode' => 'open',
        'add_mode' => 'dropdown',
        'form_display_mode' => 'default',
        'default_paragraph_type' => 'workflow_paragraph',
      ],
      'third_party_settings' => [],
      'region' => 'content',
    ]);
    $form_display->save();

    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
    $this->drupalPlaceBlock('local_tasks_block');

    $this->user = $this->setUpTranslatorUser();
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that users can only create translations of validated content.
   */
  public function testTranslationAccess(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    // Revision 1.
    $node->save();
    $this->assertEquals(1, $node->getRevisionId());

    // Ensure we can only create a translation request if the node is validated
    // or published.
    $url = Url::fromRoute('oe_translation_local.create_local_translation_request', [
      'entity_type' => $node->getEntityTypeId(),
      'entity' => 1,
      'source' => 'en',
      'target' => 'fr',
    ]);
    $this->assertFalse($url->access($this->user));

    $node->set('moderation_state', 'needs_review');
    $node->save();
    // Revision 2.
    $url->setRouteParameter('entity', 2);
    $this->assertFalse($url->access($this->user));

    $node->set('moderation_state', 'request_validation');
    $node->save();
    // Revision 3.
    $url->setRouteParameter('entity', 3);
    $this->assertFalse($url->access($this->user));

    // Navigate to the local translation overview page and assert we don't have
    // a link to start a translation.
    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    $this->assertSession()->linkNotExists('Add new translation');
    $this->assertSession()->pageTextContains('This content cannot be translated yet as it does not have a Validated nor Published major version.');

    // Validate the node.
    $node->set('moderation_state', 'validated');
    $node->save();
    // Revision 4.
    $url->setRouteParameter('entity', 4);
    $this->assertTrue($url->access($this->user));

    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    $this->assertSession()->pageTextNotContains('This content cannot be translated yet as it does not have a Validated nor Published major version.');
    $this->assertSession()->linkExists('Add new translation');

    $node->set('moderation_state', 'published');
    $node->save();
    // Revision 5.
    $url->setRouteParameter('entity', 5);
    $this->assertTrue($url->access($this->user));

    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    $this->assertSession()->pageTextNotContains('This content cannot be translated yet as it does not have a Validated nor Published major version.');
    $this->assertSession()->linkExists('Add new translation');

    // If we start a new draft, then we cannot create a new translation for that
    // draft, but we can still access the endpoint for the published one.
    $node->set('moderation_state', 'draft');
    $node->set('title', 'My node updated');
    $node->save();
    // Revision 6.
    $url->setRouteParameter('entity', 6);
    $this->assertFalse($url->access($this->user));
    $url->setRouteParameter('entity', 5);
    $this->assertTrue($url->access($this->user));

    // Assert that the user sees a message, though, that they can and would
    // actually be translating the published version of the content.
    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    $this->assertSession()->pageTextContains('Your content has revisions that are ahead of the latest published version.');
    $this->assertSession()->pageTextContains('However, you are now translating the latest published version of your content: 1.0.0, titled My node.');
  }

  /**
   * Tests that the translation dashboard shows the correct translations.
   */
  public function testTranslationDashboardExistingTranslations(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->responseContains('<h3>Existing synchronised translations</h3>');
    // Assert that before we have a published version AND a validated one,
    // the table looks normal.
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'My node'],
    ]);

    // Publish the node.
    $node = $this->moderateNode($node, 'published');

    // Translate to FR in version 1.0.0.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node in French (version 1.0.0)');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Go to the dashboard and assert the table title has been changed.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->responseContains('<h3>Existing synchronised translations</h3>');

    // Create a new draft and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();
    $node = $this->moderateNode($node, 'validated');

    // Go to the dashboard and assert our table now has columns indicating
    // translation info for each version.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertExtendedDashboardExistingTranslations([
      'en' => [
        'published_title' => 'My node',
        'validated_title' => 'My node 2',
      ],
      'fr' => [
        'published_title' => 'My node FR',
        'validated_title' => 'My node FR',
      ],
    ], ['1.0.0 / published', '2.0.0 / validated']);

    // Create a translation in IT for the published version.
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="it"] td[data-version="1.0.0"] a')->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node in Italian (version 1.0.0)');
    $values = [
      'Translation' => 'My node IT',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Go back to the dashboard and assert our table got this new language.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertExtendedDashboardExistingTranslations([
      'en' => [
        'published_title' => 'My node',
        'validated_title' => 'My node 2',
      ],
      'fr' => [
        'published_title' => 'My node FR',
        'validated_title' => 'My node FR',
      ],
      'it' => [
        'published_title' => 'My node IT',
        // Since we made the IT translation after the new version was created,
        // we don't have any translations on the new version.
        'validated_title' => 'N/A',
      ],
    ], ['1.0.0 / published', '2.0.0 / validated']);
  }

  /**
   * Tests the creation of new translations using the workflow.
   *
   * It covers the basic translation flow that ensures translations are
   * started from the validated state and if the underlying content is published
   * before the translation is synced, when the latter happens, the translation
   * will be saved onto the published revision of that version, instead of the
   * validated one from which it actually started.
   *
   * Moreover, it covers the case in which we have a validated version on top
   * of a published one, in which case we can translate both at the same time.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function testDefaultLocalModeratedTranslation(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');

    // At this point, we expect to have 4 revisions of the node.
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(4, $revision_ids);

    // Create a local translation request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node in French (version 1.0.0)');
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');

    // Assert the created request is referencing the validated revision. At
    // this point, we should have only 1 translation request.
    $request = TranslationRequest::load(1);
    $this->assertEquals($node->getRevisionId(), $request->getContentEntity()->getRevisionId());

    // Publish the node before finalizing the translation request.
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(5, $revision_ids);

    // Finalize the translation and check that the translation got saved onto
    // the published version rather than the validated one where it actually
    // got started. But to do this, go to the local translations UI and click
    // the edit button (which should in fact take us to the translation request
    // start from the validated version).
    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    $this->clickLink('Edit draft translation');
    $this->assertSession()->addressEquals('/translation-request/1/translate-locally');
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node in French (version 1.0.0)');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->get('moderation_state')->value === 'published') {
        $this->assertTrue($revision->hasTranslation('fr'), 'The published node does not have a translation');
        continue;
      }

      $this->assertFalse($revision->hasTranslation('fr'), sprintf('The %s node has a translation and it shouldn\'t', $revision->get('moderation_state')->value));
    }

    // Start a new draft from the latest published node and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(6, $revision_ids);
    $node = $this->moderateNode($node, 'validated');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(9, $revision_ids);
    // Assert that the latest revision that was just validated is the correct
    // version and inherited the translation from the previous version.
    /** @var \Drupal\node\NodeInterface $validated_node */
    $validated_node = $node_storage->loadRevision($node_storage->getLatestRevisionId($node->id()));
    $this->assertEquals('2', $validated_node->get('version')->major);
    $this->assertEquals('0', $validated_node->get('version')->minor);
    $this->assertEquals('My node FR', $validated_node->getTranslation('fr')->label());

    // Create a new local translation request for the new version (validated
    // revision).
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="2.0.0"] a')->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node 2 in French (version 2.0.0)');

    // The default translation value comes from the previous version
    // translation.
    $second_request = TranslationRequest::load(2);
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node FR');
    $this->assertEquals($validated_node->getRevisionId(), $second_request->getContentEntity()->getRevisionId());

    // Assert the dashboard contains the ongoing request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $expected = [];
    $expected[] = [
      'French',
      'Draft',
      // We started from Validated.
      '2.0.0 / validated',
      'Edit draft translationDelete',
    ];
    $this->assertLocalOngoingRequests($expected);

    // Publish the node before finalizing the translation.
    $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(10, $revision_ids);
    // Assert the dashboard contains the ongoing requests.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $expected = [];
    $expected[] = [
      'French',
      'Draft',
      // Even though the translation started from the Validated revision, we
      // published the node so we show instead "published" to be clear to the
      // user that is the state the translation values would go onto.
      '2.0.0 / published',
      'Edit draft translationDelete',
    ];
    $this->assertLocalOngoingRequests($expected);

    // Finalize the translation and check that the translation got saved onto
    // the published version rather than the validated one where it actually
    // got started.
    $values = [
      'Translation' => 'My node 2 FR',
    ];
    $this->drupalGet($second_request->toUrl('local-translation'));
    $this->submitForm($values, t('Save and synchronise'));
    $node_storage->resetCache();
    $validated_node = $node_storage->loadRevision($validated_node->getRevisionId());
    // The second validated revision should have the old FR translation.
    $this->assertEquals('My node FR', $validated_node->getTranslation('fr')->label());
    $node = $node_storage->load($node->id());
    // The new (current) published revision should have the new FR translation.
    $this->assertEquals('My node 2 FR', $node->getTranslation('fr')->label());

    // The previous published revisions have the old FR translation.
    $revision_ids = $node_storage->revisionIds($node);
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->isPublished() && (int) $revision->get('version')->major === 1) {
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label());
        break;
      }
    }

    // Create a new draft and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 3');
    $node->set('moderation_state', 'draft');
    $node->save();
    $this->moderateNode($node, 'validated');
    $revision_ids = $node_storage->revisionIds($node);
    // We have 14 new revisions now.
    $this->assertCount(14, $revision_ids);
    // Test the UI changes when we have a published version and we make a new
    // validated one which increases the major version and which means we can
    // now translate both the published version and the new validated major.
    $this->drupalGet(Url::fromRoute('entity.node.local_translation', ['node' => $node->id()]));
    // Assert we see a message telling the user what's what.
    $this->assertSession()->pageTextContains('Your content has a new version (3.0.0) ahead of the currently published one (2.0.0). This means you can perform translations on both.');
    $this->assertSession()->pageTextContains('Be aware that any translations you change or add to the published version will not move upstream to the new version.');

    // Assert the published and validated revision situation.
    $published = $node_storage->load($node->id());
    $this->assertEquals('published', $published->get('moderation_state')->value);
    $this->assertEquals('2.0.0', $this->getEntityVersion($published));
    $validated = $node_storage->loadRevision($node_storage->getLatestRevisionId($node->id()));
    $this->assertEquals('validated', $validated->get('moderation_state')->value);
    $this->assertEquals('3.0.0', $this->getEntityVersion($validated));

    // Create a new translation for each and save them both as drafts.
    $create_link_published = $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="2.0.0"] a');
    $this->assertEquals('Add new translation', $create_link_published->getText());
    $create_link_published->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node 2 in French (version 2.0.0)');
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node 2 FR');
    $values = [
      'Translation' => 'My node 2 FR (updated but no actual change cause we already have a translation)',
    ];
    $this->submitForm($values, t('Save as draft'));
    $this->assertSession()->pageTextContains('The translation has been saved.');
    // Assert we now have an edit link instead.
    $edit_link_published = $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="2.0.0"] a');
    $this->assertEquals('Edit draft translation', $edit_link_published->getText());
    $requests = \Drupal::entityTypeManager()->getStorage('oe_translation_request')->getTranslationRequestsForEntityRevision($published, 'local');
    $published_request = end($requests);
    $this->assertEquals($published_request->toUrl('local-translation', ['query' => ['destination' => '/build/node/' . $node->id() . '/translations/local']])->toString(), $edit_link_published->getAttribute('href'));
    // Now for the validated.
    $create_link_validated = $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="3.0.0"] a');
    $this->assertEquals('Add new translation', $create_link_validated->getText());
    $create_link_validated->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node 3 in French (version 3.0.0)');
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node 2 FR');
    $values = [
      'Translation' => 'My node 3 FR (brand new translation)',
    ];
    $this->submitForm($values, t('Save as draft'));
    $this->assertSession()->pageTextContains('The translation has been saved.');
    // Assert we now have an edit link instead.
    $edit_link_validated = $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="3.0.0"] a');
    $this->assertEquals('Edit draft translation', $edit_link_validated->getText());
    $requests = \Drupal::entityTypeManager()->getStorage('oe_translation_request')->getTranslationRequestsForEntityRevision($validated, 'local');
    $validated_request = end($requests);
    $this->assertEquals($validated_request->toUrl('local-translation', ['query' => ['destination' => '/build/node/' . $node->id() . '/translations/local']])->toString(), $edit_link_validated->getAttribute('href'));
    // Assert the dashboard contains the ongoing requests.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $expected = [];
    $expected[] = [
      'French',
      'Draft',
      '2.0.0 / published',
      'Edit draft translationDelete',
    ];
    $expected[] = [
      'French',
      'Draft',
      '3.0.0 / validated',
      'Edit draft translationDelete',
    ];
    $this->assertLocalOngoingRequests($expected);
    // Sync the translation requests.
    $this->clickLink('Local translations');
    $edit_link_published->click();
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->pageTextContains('The translation has been synchronised.');
    $edit_link_validated->click();
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation has been saved.');
    $this->assertSession()->pageTextContains('The translation has been synchronised.');
    // Assert the translations got saved on the correct revisions.
    $revision_ids = $node_storage->revisionIds($node);
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->isPublished() && (int) $revision->get('version')->major === 1) {
        // The very first version which we didn't touch this time around.
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label());
        break;
      }
      if ($revision->isPublished() && (int) $revision->get('version')->major === 2) {
        // The published version whose translation we updated.
        $this->assertEquals('My node 2 FR (updated but no actual change cause we already have a translation)', $revision->getTranslation('fr')->label());
        break;
      }
      if ($revision->isPublished() && (int) $revision->get('version')->major === 3) {
        // The validated version whose translation we just created.
        $this->assertEquals('My node 3 FR (brand new translation)', $revision->getTranslation('fr')->label());
        break;
      }
    }

    // Assert there are no more non-synced translation requests.
    /** @var \Drupal\oe_translation_local\TranslationRequestLocal[] $requests */
    $requests = TranslationRequest::loadMultiple();
    foreach ($requests as $request) {
      $this->assertEquals(TranslationRequestLocal::STATUS_LANGUAGE_SYNCHRONISED, $request->getTargetLanguageWithStatus()->getStatus());
    }
  }

  /**
   * Tests that revision translations are carried over from latest revision.
   *
   * The test focuses on ensuring that when a new revision is created by the
   * storage based on another one, the new one inherits the translated values
   * from the one its based on and NOT from the latest default revision as core
   * would have it.
   *
   * @see oe_translation_corporate_workflow_entity_revision_create()
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function testTranslationRevisionsCarryOver(): void {
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
    $node = $this->moderateNode($node, 'validated');
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    $node = $node_storage->load($node->id());
    // Publish the node and check that the translation is available in the
    // published revision.
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);

    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    // Since we translated the node while it was validated, both revisions
    // should contain the same translation.
    foreach ($revisions as $revision) {
      if ($revision->isPublished() || $revision->get('moderation_state')->value === 'validated') {
        $this->assertTrue($revision->hasTranslation('fr'), 'The revision does not have a translation');
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label(), 'The revision does not have a correct translation');
      }
    }

    // Start a new draft from the latest published node and validate it.
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();

    $node = $this->moderateNode($node, 'validated');
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));

    // The default translation value comes from the previous version
    // translation.
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node FR');
    $values = [
      'Translation' => 'My node 2 FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Publish the node and check that the published versions have the correct
    // translations. Since we have previously published revisions, we need to
    // use the latest revision to transition to the published state.
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $node = $node_storage->loadRevision($revision_id);
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);

    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->isPublished() && (int) $revision->get('version')->major === 1) {
        $this->assertEquals('My node FR', $revision->getTranslation('fr')->label());
        continue;
      }

      if ($revision->isPublished() && (int) $revision->get('version')->major === 2) {
        $this->assertEquals('My node 2 FR', $revision->getTranslation('fr')->label());
        continue;
      }
    }

    // Test that if the default revision of the content
    // has less translations than the revision where we make a new revision
    // from, the new revision will include all the translations from the
    // previous revision not only the ones from the default revision.
    // Start a new draft from the latest published node and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 3');
    $node->set('moderation_state', 'draft');
    $node->save();

    $node = $this->moderateNode($node, 'validated');
    // Create some more translations.
    foreach (['fr', 'it', 'ro'] as $langcode) {
      $request = $this->createLocalTranslationRequest($node, $langcode);
      $this->drupalGet($request->toUrl('local-translation'));

      $values = [
        'Translation' => "My node $langcode 3",
      ];
      $this->submitForm($values, t('Save and synchronise'));
    }

    // Publish the content and assert that the new published version has
    // translations in 4 languages.
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $node = $node_storage->loadRevision($revision_id);
    $node = $this->moderateNode($node, 'published');
    foreach (['fr', 'it', 'ro'] as $langcode) {
      $this->assertTrue($node->hasTranslation($langcode), 'Translation missing in ' . $langcode);
    }

    // Test that translations carry over works also with embedded entities.
    // These are entities such as paragraphs which are considered as composite,
    // depend on the parent via the entity_reference_revisions
    // entity_revision_parent_id_field.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display */
    $paragraph = Paragraph::create([
      'type' => 'workflow_paragraph',
      'field_workflow_paragraph_text' => 'the paragraph text value',
    ]);
    $paragraph->save();

    $node = Node::create([
      'type' => 'oe_workflow_demo',
      'title' => 'Node with a paragraph',
      'field_workflow_paragraphs' => [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ],
    ]);

    $node->save();
    // Publish the node.
    $node = $this->moderateNode($node, 'published');

    // Add a translation to the published version.
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'Node with a paragraph');
    $this->assertSession()->elementContains('css', '#edit-field-workflow-paragraphs0entityfield-workflow-paragraph-text0value-translation', 'the paragraph text value');
    $values = [
      'title|0|value[translation]' => 'Node with a paragraph FR',
      'field_workflow_paragraphs|0|entity|field_workflow_paragraph_text|0|value[translation]' => 'the paragraph text value FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Make a new draft and change the node and paragraph.
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $this->drupalGet($node->toUrl());
    $this->clickLink('New draft');
    $this->getSession()->getPage()->fillField('title[0][value]', 'Node with a paragraph - updated');
    $this->getSession()->getPage()->fillField('field_workflow_paragraphs[0][subform][field_workflow_paragraph_text][0][value]', 'the paragraph text value - updated');
    $this->getSession()->getPage()->pressButton('Save (this translation)');
    $this->assertSession()->pageTextContains('Node with a paragraph - updated has been updated.');
    // Validate the node.
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Validated');
    // Submitting the form will trigger a batch, so use the correct method to
    // account for redirects.
    $this->submitForm([], 'Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    // Update also the translation. We make sure we translate the validated
    // version and not the published one.
    $this->clickLink('Translate');
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] td[data-version="2.0.0"] a')->click();
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'Node with a paragraph FR');
    $this->assertSession()->elementContains('css', '#edit-field-workflow-paragraphs0entityfield-workflow-paragraph-text0value-translation', 'the paragraph text value FR');
    $values = [
      'title|0|value[translation]' => 'Node with a paragraph FR 2',
      'field_workflow_paragraphs|0|entity|field_workflow_paragraph_text|0|value[translation]' => 'the paragraph text value FR 2',
    ];
    $this->submitForm($values, t('Save and synchronise'));
    // Go back to the node and publish it.
    $this->drupalGet($node->toUrl());
    $this->clickLink('View draft');
    $this->getSession()->getPage()->selectFieldOption('Change to', 'Published');
    $this->getSession()->getPage()->pressButton('Apply');
    $this->assertSession()->pageTextContains('The moderation state has been updated.');
    $node_storage->resetCache();
    $revision_id = $node_storage->getLatestRevisionId($node->id());
    $revision = $node_storage->loadRevision($revision_id);
    $node_translation = $revision->getTranslation('fr');
    $this->assertEquals('Node with a paragraph - updated', $revision->label());
    $this->assertEquals('Node with a paragraph FR 2', $node_translation->label());
    $paragraph = $revision->get('field_workflow_paragraphs')->entity;
    $paragraph_translation = $paragraph->getTranslation('fr');
    $this->assertEquals('the paragraph text value - updated', $paragraph->get('field_workflow_paragraph_text')->value);
    $this->assertEquals('the paragraph text value FR 2', $paragraph_translation->get('field_workflow_paragraph_text')->value);
  }

  /**
   * Tests that moderation state translations are kept in sync with original.
   */
  public function testModerationStateSync(): void {
    // Create a validated node and add a translation.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'validated');
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Assert that the node has two translations and the moderation state entity
    // also has two translations.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertCount(2, $node->getTranslationLanguages());
    $moderation_state = ContentModerationState::loadFromModeratedEntity($node);
    $this->assertCount(2, $moderation_state->getTranslationLanguages());

    // Assert that both the node and moderation state translations are
    // "validated".
    $assert_translation_state = function (ContentEntityInterface $entity, $state) {
      foreach ($entity->getTranslationLanguages() as $language) {
        $translation = $entity->getTranslation($language->getId());
        $this->assertEquals($state, $translation->get('moderation_state')->value, sprintf('The %s language has the %s state', $language->getName(), $state));
      }
    };
    $assert_translation_state($node, 'validated');
    $assert_translation_state($moderation_state, 'validated');

    // "Break" the system by deleting the moderation state entity translation.
    $moderation_state->removeTranslation('fr');
    ContentModerationState::updateOrCreateFromEntity($moderation_state);

    // Now only the original will be validated, and the translation of the node
    // becomes "draft" because it no longer is translated. This situation
    // should not really occur, but if it does, it can break new translations
    // which when being saved onto the node, cause the moderation state of
    // the original to be set to draft instead of keeping it on validated.
    $node_storage->resetCache();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->load($node->id());
    $this->assertEquals('validated', $node->get('moderation_state')->value);
    $this->assertEquals('draft', $node->getTranslation('fr')->get('moderation_state')->value);

    // Create a new translation.
    $request = $this->createLocalTranslationRequest($node, 'fr');
    $this->drupalGet($request->toUrl('local-translation'));
    $this->assertSession()->elementContains('css', '#edit-title0value-translation', 'My node');
    $values = [
      'Translation' => 'My node FR 2',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Assert the node is still in validated state and the content moderation
    // state entity got its translation back.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $assert_translation_state($node, 'validated');
    $moderation_state = ContentModerationState::loadFromModeratedEntity($node);
    $this->assertCount(2, $moderation_state->getTranslationLanguages());
    $assert_translation_state($moderation_state, 'validated');
  }

  /**
   * Tests that synchronising translations doesn't change the default revision.
   *
   * The tested use case is having a translation started from a Published
   * version that becomes unpublished, followed by syncing that ongoing
   * translation. We ensure that when we sync onto that Published version which
   * is no longer the default revision, it doesn't become the default revision.
   */
  public function testDefaultRevisionFlagIsKept(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(5, $revision_ids);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    // Save the translation request as draft.
    $this->submitForm([], t('Save as draft'));
    $node_storage->resetCache();

    // Unpublish the node.
    $node->set('moderation_state', 'archived');
    $node->setNewRevision(TRUE);
    $node->save();
    $revision_ids = $node_storage->revisionIds($node);
    // Assert we have one extra revision and the default revision is the
    // Archived one and not the Published one.
    $this->assertCount(6, $revision_ids);
    $node = $node_storage->load($node->id());
    $this->assertEquals('archived', $node->get('moderation_state')->value);
    $this->assertTrue($node->isDefaultRevision());
    array_pop($revision_ids);
    $published_revision_id = array_pop($revision_ids);
    $published = $node_storage->loadRevision($published_revision_id);
    $this->assertEquals('published', $published->get('moderation_state')->value);
    $this->assertFalse($published->isDefaultRevision());

    // Synchronise the translation.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Edit draft translation');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));
    $node_storage->resetCache();

    // Assert we don't have extra revisions created, nor is the default
    // revision published.
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(6, $revision_ids);
    $node = $node_storage->load($node->id());
    $this->assertEquals('archived', $node->get('moderation_state')->value);
    $this->assertTrue($node->isDefaultRevision());
    $this->assertCount(1, $node->getTranslationLanguages());
    // The published revision is still not default but has the translation on
    // it.
    array_pop($revision_ids);
    $published_revision_id = array_pop($revision_ids);
    $published = $node_storage->loadRevision($published_revision_id);
    $this->assertEquals('published', $published->get('moderation_state')->value);
    $this->assertFalse($published->isDefaultRevision());
    $this->assertCount(2, $published->getTranslationLanguages());
  }

  /**
   * Tests that node translations keep track of the translation requests.
   *
   * This tests that we keep track of which are the translations that have been
   * created as part of a synchronisation and focuses on the local translation.
   */
  public function testTranslationRequestTrackingLocal(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(5, $revision_ids);

    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    $this->submitForm([], t('Save and synchronise'));
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    // The EN version doesn't have the reference.
    $this->assertNull($node->get('translation_request')->entity);
    $this->assertInstanceOf(TranslationRequestInterface::class, $node->getTranslation('fr')->get('translation_request')->entity);
    // Keep track of the translation request.
    $version_one_request = $node->getTranslation('fr')->get('translation_request')->entity;

    // Make a new draft and assert the reference is not kept.
    $node->set('moderation_state', 'draft');
    $node->setNewRevision(TRUE);
    $node->save();
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(6, $revision_ids);

    $node_storage->resetCache();
    // This is still the published version.
    $node = $node_storage->load($node->id());
    $this->assertNull($node->get('translation_request')->entity);
    $this->assertInstanceOf(TranslationRequestInterface::class, $node->getTranslation('fr')->get('translation_request')->entity);
    // This is the new draft.
    $node = $node_storage->loadRevision($node_storage->getLatestRevisionId($node->id()));
    $this->assertNull($node->get('translation_request')->entity);
    $this->assertNull($node->getTranslation('fr')->get('translation_request')->entity);
    // Published the draft.
    $node = $this->moderateNode($node, 'published');
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(10, $revision_ids);

    // Translate again the node.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    $this->submitForm([], t('Save and synchronise'));
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    // The EN version doesn't have the reference.
    $this->assertNull($node->get('translation_request')->entity);
    $this->assertInstanceOf(TranslationRequestInterface::class, $node->getTranslation('fr')->get('translation_request')->entity);
    $version_two_request = $node->getTranslation('fr')->get('translation_request')->entity;
    $this->assertNotEquals($version_one_request->id(), $version_two_request->id());
  }

  /**
   * Tests that we can delete a revision translation individually.
   */
  public function testTranslationRevisionDelete() {
    $role = Role::load('oe_translator');
    $role->grantPermission('delete any page content');
    $role->grantPermission('delete content translations');
    $role->grantPermission('delete all revisions');
    $role->save();

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();
    $node = $this->moderateNode($node, 'published');

    // Translate to FR in version 1.0.0.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Local translations');
    $this->getSession()->getPage()->find('css', 'tr[hreflang="fr"] a')->click();
    $this->assertSession()->elementTextEquals('css', 'h1', 'Translate My node in French (version 1.0.0)');
    $values = [
      'Translation' => 'My node FR',
    ];
    $this->submitForm($values, t('Save and synchronise'));

    // Create a new draft and validate it.
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();
    $node = $this->moderateNode($node, 'validated');

    // Go to the dashboard and assert our table now has columns indicating
    // translation info for each version.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertExtendedDashboardExistingTranslations([
      'en' => [
        'published_title' => 'My node',
        'validated_title' => 'My node 2',
      ],
      'fr' => [
        'published_title' => 'My node FR',
        'validated_title' => 'My node FR',
      ],
    ], ['1.0.0 / published', '2.0.0 / validated']);

    // Count all the node revisions and their translations to establish a
    // baseline.
    $revision_ids = $node_storage->getQuery()->allRevisions()->accessCheck(FALSE)->execute();
    $revisions = $node_storage->loadMultipleRevisions(array_keys($revision_ids));
    $this->assertCount(9, $revisions);
    $non_translated_revision_ids = [1, 2, 3, 4];
    $translated_revision_ids = [5, 6, 7, 8, 9];
    foreach ($non_translated_revision_ids as $id) {
      $revision = $node_storage->loadRevision($id);
      $this->assertFalse($revision->hasTranslation('fr'));
    }
    foreach ($translated_revision_ids as $id) {
      $revision = $node_storage->loadRevision($id);
      $this->assertTrue($revision->hasTranslation('fr'));
    }

    // Delete the validated FR translation.
    $this->getSession()->getPage()->find('xpath', '//table[@class="existing-translations-table"]//tr[@hreflang="fr"]//td[5]')->clickLink('Delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete the French translation of the revision');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->pageTextContains(sprintf('Revision translation in French from %s of My node FR has been deleted.', \Drupal::service('date.formatter')->format($node_storage->loadRevision(9)->getRevisionCreationTime())));
    $this->assertSession()->addressEquals('/node/' . $node->id() . '/translations');
    // The last revision no longer has a translation and there are the same
    // number of revisions in the system.
    $non_translated_revision_ids = [1, 2, 3, 4, 9];
    $translated_revision_ids = [5, 6, 7, 8];
    foreach ($non_translated_revision_ids as $id) {
      $revision = $node_storage->loadRevision($id);
      $this->assertFalse($revision->hasTranslation('fr'));
    }
    foreach ($translated_revision_ids as $id) {
      $revision = $node_storage->loadRevision($id);
      $this->assertTrue($revision->hasTranslation('fr'));
    }
    $revisions = $node_storage->loadMultipleRevisions(array_keys($revision_ids));
    $this->assertCount(9, $revisions);
  }

  /**
   * Asserts the existing translations table.
   *
   * @param array $languages
   *   The expected languages.
   * @param array $versions
   *   The expected versions.
   */
  protected function assertExtendedDashboardExistingTranslations(array $languages, array $versions): void {
    $table = $this->getSession()->getPage()->find('css', 'table.existing-translations-table');
    $this->assertCount(count($languages), $table->findAll('css', 'tbody tr'));
    $header = $table->findAll('css', 'thead th');
    $this->assertEquals('Language', $header[0]->getText());
    $this->assertEquals($versions[0], $header[1]->getText());
    $this->assertEquals('Operations', $header[2]->getText());
    $this->assertEquals($versions[1], $header[3]->getText());
    $this->assertEquals('Operations', $header[4]->getText());

    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $row) {
      $cols = $row->findAll('css', 'td');
      $hreflang = $row->getAttribute('hreflang');
      $expected_info = $languages[$hreflang];
      $language = ConfigurableLanguage::load($hreflang);
      $this->assertEquals($language->getName(), $cols[0]->getText());
      if ($expected_info['published_title'] === 'N/A') {
        $this->assertEquals('N/A', $cols[1]->getText());
      }
      else {
        $this->assertNotNull($cols[1]->findLink($expected_info['published_title']));
      }

      if ($expected_info['validated_title'] === 'N/A') {
        $this->assertEquals('N/A', $cols[3]->getText());
      }
      else {
        $this->assertNotNull($cols[3]->findLink($expected_info['validated_title']));
      }
    }
  }

  /**
   * Asserts the ongoing local requests table values.
   *
   * @param array $expected
   *   The expected requests data.
   */
  protected function assertLocalOngoingRequests(array $expected): void {
    $table = $this->getSession()->getPage()->find('css', 'table.ongoing-local-translation-requests-table');
    foreach ($expected as $row_key => $cols) {
      $row = $table->findAll('css', 'tbody tr')[$row_key];
      $columns = $row->findAll('css', 'td');
      foreach ($cols as $col_key => $col_value) {
        $col = $columns[$col_key];
        $this->assertEquals($col_value, $col->getText(), sprintf('The %s column value is not correct', $col_value));
      }
    }
  }

}
