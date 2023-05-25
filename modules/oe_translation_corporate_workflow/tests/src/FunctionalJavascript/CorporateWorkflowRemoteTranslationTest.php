<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_corporate_workflow\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_remote_test\TestRemoteTranslationMockHelper;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;

/**
 * Tests the remote translation revision capability.
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
class CorporateWorkflowRemoteTranslationTest extends WebDriverTestBase {

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
    'oe_translation_remote',
    'oe_translation_remote_test',
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
  protected function setUp(): void {
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
   * Tests the creation of new translations using the workflow.
   *
   * It covers the basic translation flow that ensures translations are
   * started from the validated state and if the underlying content is published
   * before the translation is synced, when the latter happens, the translation
   * will be saved onto the published revision of that version, instead of the
   * validated one from which it actually started.
   */
  public function testDefaultRemoteModeratedTranslation(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My node',
      'moderation_state' => 'draft',
    ]);
    $node->save();

    // Assert we cannot translate this node while in draft.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->elementContains('css', '.translator-wrapper .messages--warning', 'This content cannot be translated yet as it does not have a Validated nor Published major version.');

    // Validate the node and make a new translation request in Bulgarian.
    $node = $this->moderateNode($node, 'validated');
    $validated_id = $node->getRevisionId();
    $this->getSession()->reload();
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->assertSession()->elementTextEquals('css', 'form h3', 'Ongoing remote translation request via Remote one for version 1.0.0 in moderation state validated.');

    // We can no longer make a new translation because we have an active one.
    $this->assertSession()->elementContains('css', '.translator-wrapper .messages--warning', 'No new translation request can be made because there is already an active translation request for this entity version.');
    $this->assertSession()->fieldDisabled('Translator');

    // Assert the request status table.
    $this->assertRequestStatusTable([
      'Requested',
      'Remote one',
      '1.0.0 / validated',
    ]);

    // Publish the node.
    $node = $this->moderateNode($node, 'published');
    $validated = $node_storage->loadRevision($validated_id);
    $revision_ids = $node_storage->revisionIds($node);
    $this->assertCount(5, $revision_ids);

    // Visit the dashboard and the remote translation requests page and assert
    // we can see the request started in the validated stage.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $expected_ongoing = [
      'translator' => 'Remote one',
      'status' => 'Requested',
      // Even though the translation started from the Validated revision, we
      // published the node so we show instead "published" to be clear to the
      // user that is the state the translation values would go onto.
      'version' => '1.0.0 / published',
    ];
    $this->assertOngoingTranslations([$expected_ongoing]);
    $this->clickLink('Remote translations');
    $this->assertRequestStatusTable([
      'Requested',
      'Remote one',
      '1.0.0 / published',
    ]);

    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, TRUE);
    // We don't have any requests for the published revision, but we do have
    // for the validated one.
    $this->assertCount(0, $requests);
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, FALSE);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr');
    $request->save();

    $this->getSession()->reload();

    // The only language in the request has been translated so the request
    // itself is now translated which means new translation requests can again
    // be made.
    $this->assertSession()->fieldEnabled('Translator');
    $this->assertRequestStatusTable([
      'Translated',
      'Remote one',
      '1.0.0 / published',
    ]);

    $this->clickLink('Dashboard');
    $expected_ongoing['status'] = 'Translated';
    $this->assertOngoingTranslations([$expected_ongoing]);

    // Sync the translation onto the node.
    $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table')->clickLink('View');
    $this->assertRequestStatusTable([
      'Translated',
      'Remote one',
      '1.0.0 / published',
    ]);
    $this->getSession()->getPage()->find('css', 'table tbody tr[hreflang="fr"] a')->click();
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->assertSession()->addressEquals('/translation-request/' . $request->id());

    // Assert that we now make new translations and there are no more
    // existing translation requests.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->assertSession()->fieldEnabled('Translator');
    $this->assertSession()->elementNotExists('css', 'table.ongoing-remote-translation-requests-table');
    $this->assertSession()->pageTextNotContains('Ongoing remote translation requests');
    $this->clickLink('Dashboard');
    $this->assertSession()->pageTextContains('There are no ongoing remote translation requests');
    $this->assertDashboardExistingTranslations([
      'en' => ['title' => 'My node'],
      'fr' => ['title' => 'My node - fr'],
    ]);

    $node_storage->resetCache();
    // Assert that even though the translation started from the validated
    // revision, it got synced onto the published one.
    /** @var \Drupal\node\NodeInterface[] $revisions */
    $revisions = $node_storage->loadMultipleRevisions($revision_ids);
    foreach ($revisions as $revision) {
      if ($revision->get('moderation_state')->value === 'published') {
        $this->assertTrue($revision->hasTranslation('fr'), 'The published node does not have a translation');
        continue;
      }

      $this->assertFalse($revision->hasTranslation('fr'), sprintf('The %s node has a translation and it shouldn\'t', $revision->get('moderation_state')->value));
    }
  }

  /**
   * Tests the creation of new translations using the workflow.
   *
   * This tests translation handling for when there are forward revisions
   * after a published version.
   */
  public function testForwardRevisionRemoteModeratedTranslation(): void {
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
    $this->assertCount(5, $node_storage->revisionIds($node));
    $published_revision_id = $node->getRevisionId();

    // Start a translation for the published node.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->assertSession()->elementTextEquals('css', 'form h3', 'Ongoing remote translation request via Remote one for version 1.0.0 in moderation state published.');
    // We can no longer make a new translation because we have an active one.
    $this->assertSession()->elementContains('css', '.translator-wrapper .messages--warning', 'No new translation request can be made because there is already an active translation request for this entity version.');
    $this->assertSession()->fieldDisabled('Translator');

    // Translate it.
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node);
    $this->assertCount(1, $requests);
    $request = reset($requests);
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr', 'first');
    $request->save();

    // Reload the page and assert we can now translate again.
    $this->getSession()->reload();
    $this->assertSession()->fieldEnabled('Translator');

    // Start a new draft from the node.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $node->set('title', 'My node 2');
    $node->set('moderation_state', 'draft');
    $node->save();
    $this->assertCount(6, $node_storage->revisionIds($node));

    $this->getSession()->reload();

    // Assert that we now see a message informing the user that there is a new
    // draft but that they would be translating the published version.
    $this->assertSession()->pageTextContains('Your content has revisions that are ahead of the latest published version.');
    $this->assertSession()->pageTextContains('However, you are now translating the latest published version of your content: 1.0.0, titled My node.');

    // Start a new translation and assert the new translation request is using
    // the published version of the node.
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, FALSE);
    // We now have 2 request for this node.
    $this->assertCount(2, $requests);
    $request = array_pop($requests);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $this->assertEquals($published_revision_id, $request->getContentEntity()->getRevisionId());

    $this->getSession()->reload();
    $this->assertSession()->fieldDisabled('Translator');

    $first_ongoing = [
      'translator' => 'Remote one',
      'status' => 'Translated',
      'version' => '1.0.0 / published',
    ];
    $second_ongoing = $first_ongoing;
    $second_ongoing['status'] = 'Requested';
    $this->assertOngoingTranslations([$first_ongoing, $second_ongoing]);

    // Validate the draft and assert we still cannot request a new translation
    // because we have an active one.
    $node = $this->moderateNode($node, 'validated');
    $this->assertCount(9, $node_storage->revisionIds($node));
    $this->getSession()->reload();
    $this->assertSession()->fieldDisabled('Translator');
    $this->assertSession()->elementContains('css', '.translator-wrapper .messages--warning', 'No new translation request can be made because there is already an active translation request for this entity version.');

    // Translate the active request.
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr', 'second');
    $request->save();

    $this->getSession()->reload();

    // We now can make new translation requests again.
    $this->assertSession()->fieldEnabled('Translator');
    // But we see a message informing the user that a new version has been
    // created and if they translate the content now, this new version will be
    // used.
    $this->assertSession()->pageTextContains('Your content has a new version (2.0.0) ahead of the currently published one (1.0.0). This means that new translation requests will be made for the new version.');

    // Start a new translation.
    $this->getSession()->getPage()->selectFieldOption('Translator', 'remote_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('French');
    $this->getSession()->getPage()->pressButton('Save and send');
    $this->assertSession()->pageTextContains('The translation request has been sent');
    $this->assertSession()->fieldDisabled('Translator');
    $requests = \Drupal::service('plugin.manager.oe_translation_remote.remote_translation_provider_manager')->getExistingTranslationRequests($node, FALSE);
    // We now have 3 request for this node.
    $this->assertCount(3, $requests);
    // Get the active request and assert the new validated revision was used.
    $request = array_pop($requests);
    $this->assertEquals('Requested', $request->getRequestStatus());
    $validated_revision_id = $node_storage->getLatestRevisionId($node->id());
    $validated = $node_storage->loadRevision($validated_revision_id);
    $this->assertEquals($validated_revision_id, $request->getContentEntity()->getRevisionId());
    $second_ongoing['status'] = 'Translated';
    $third_ongoing = [
      'translator' => 'Remote one',
      'status' => 'Requested',
      'version' => '2.0.0 / validated',
    ];
    $this->assertOngoingTranslations([
      $first_ongoing,
      $second_ongoing,
      $third_ongoing,
    ]);

    // Translate the new request.
    TestRemoteTranslationMockHelper::translateRequest($request, 'fr', 'third');
    $request->save();
    $this->getSession()->reload();
    $this->assertSession()->fieldEnabled('Translator');

    // Start syncing the translations, one by one, and assert the values on the
    // node.
    $this->getSession()->getPage()->find('xpath', '//table[@class="ongoing-remote-translation-requests-table"]/tbody/tr[1]')->clickLink('View');
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');
    // Only 2 are left.
    $third_ongoing['status'] = 'Translated';
    $this->assertOngoingTranslations([$second_ongoing, $third_ongoing]);
    // Assert the translation value.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertEquals($published_revision_id, $node->getRevisionId());
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->hasTranslation('fr'));
    $this->assertEquals('My node - fr - first', $node->getTranslation('fr')->label());
    // The validated one doesn't have a translation.
    $validated = $node_storage->loadRevision($validated_revision_id);
    $this->assertFalse($validated->hasTranslation('fr'));

    $this->getSession()->getPage()->find('xpath', '//table[@class="ongoing-remote-translation-requests-table"]/tbody/tr[1]')->clickLink('View');
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->clickLink('Remote translations');

    // Only 1 is left, so now we don't have the requests table but directly
    // the last request meta information.
    $this->assertRequestStatusTable([
      'Translated',
      'Remote one',
      '2.0.0 / validated',
    ]);
    // Assert the translation value.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertEquals($published_revision_id, $node->getRevisionId());
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->hasTranslation('fr'));
    // The translation has been updated on the same published revision.
    $this->assertEquals('My node - fr - second', $node->getTranslation('fr')->label());
    // The validated one still doesn't have a translation.
    $validated = $node_storage->loadRevision($validated_revision_id);
    $this->assertFalse($validated->hasTranslation('fr'));

    // Sync the last one.
    $this->getSession()->getPage()->clickLink('Review');
    $this->getSession()->getPage()->pressButton('Save and synchronise');
    $this->assertSession()->pageTextContains('The translation in French has been synchronised.');

    // Assert that the published revision kept its translation value.
    $node_storage->resetCache();
    $node = $node_storage->load($node->id());
    $this->assertEquals($published_revision_id, $node->getRevisionId());
    $this->assertEquals('published', $node->get('moderation_state')->value);
    $this->assertTrue($node->hasTranslation('fr'));
    // The translation has been updated on the same published revision.
    $this->assertEquals('My node - fr - second', $node->getTranslation('fr')->label());
    // But that the validated one got the translation value meant for it.
    $validated = $node_storage->loadRevision($validated_revision_id);
    $this->assertTrue($validated->hasTranslation('fr'));
    $this->assertEquals('My node 2 - fr - third', $validated->getTranslation('fr')->label());
  }

  /**
   * Asserts the ongoing translations table.
   *
   * @param array $languages
   *   The expected languages.
   */
  protected function assertOngoingTranslations(array $languages): void {
    $table = $this->getSession()->getPage()->find('css', 'table.ongoing-remote-translation-requests-table');
    $this->assertCount(count($languages), $table->findAll('css', 'tbody tr'));
    $rows = $table->findAll('css', 'tbody tr');
    foreach ($rows as $key => $row) {
      $cols = $row->findAll('css', 'td');
      $expected_info = $languages[$key];
      $this->assertEquals($expected_info['translator'], $cols[0]->getText());
      $this->assertRequestStatus($expected_info['status'], $cols[1]);
      $this->assertEquals($expected_info['version'], $cols[2]->getText());
      $this->assertTrue($cols[3]->hasLink('View'));
    }
  }

}
