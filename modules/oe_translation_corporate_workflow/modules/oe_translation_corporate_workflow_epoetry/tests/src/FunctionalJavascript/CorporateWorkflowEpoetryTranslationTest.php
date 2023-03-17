<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_translation_corporate_workflow\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_translation_corporate_workflow\CorporateWorkflowTranslationTrait;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\Entity\RemoteTranslatorProvider;
use Drupal\Tests\oe_editorial_corporate_workflow\Traits\CorporateWorkflowTrait;
use Drupal\Tests\oe_translation\Traits\TranslationsTestTrait;
use Drupal\Tests\oe_translation_epoetry\EpoetryTranslationTestTrait;

/**
 * Tests the ePoetry translations with corporate workflow.
 *
 * It only covers ePoetry specific things that are not covered as part of the
 * generic remote translation tests.
 */
class CorporateWorkflowEpoetryTranslationTest extends WebDriverTestBase {

  use CorporateWorkflowTrait;
  use CorporateWorkflowTranslationTrait;
  use TranslationsTestTrait;
  use EpoetryTranslationTestTrait;

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
    'oe_translation_epoetry',
    'oe_translation_corporate_workflow_epoetry',
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

    $this->drupalPlaceBlock('page_title_block', ['region' => 'content']);
    $this->drupalPlaceBlock('local_tasks_block');

    $provider = RemoteTranslatorProvider::load('epoetry');
    $configuration = $provider->getProviderConfiguration();
    $configuration['title_prefix'] = 'A title prefix';
    $configuration['site_id'] = 'A site ID';
    $configuration['auto_accept'] = FALSE;
    $provider->setProviderConfiguration($configuration);
    $provider->save();

    $this->user = $this->setUpTranslatorUser();
    $this->drupalLogin($this->user);
  }

  /**
   * Tests the access to create new version requests for ongoing requests.
   */
  public function testCorporateWorkflowOngoingCreateNewVersionAccess(): void {
    $cases = [
      'no new draft, sent to DGT request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SENT,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'no new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => FALSE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'new draft, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'validated, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
        'moderation_state' => 'validated',
      ],
      'published, accepted request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
        'moderation_state' => 'published',
      ],
      'new draft, executed request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
      'new draft, suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => TRUE,
        'finished' => FALSE,
      ],
      'validated, suspended request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
        'create draft' => TRUE,
        'visible' => TRUE,
        'ongoing' => TRUE,
        'finished' => FALSE,
        'moderation_state' => 'validated',
      ],
      'new draft, cancelled request' => [
        'epoetry_status' => TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
        'create draft' => TRUE,
        'visible' => FALSE,
        'ongoing' => FALSE,
        'finished' => TRUE,
      ],
    ];

    foreach ($cases as $case => $case_info) {
      // Create the node from scratch.
      $node = $this->createBasicTestNode('page');

      // Make an active request and set the case epoetry status.
      $request = $this->createNodeTranslationRequest($node);
      $request->setEpoetryRequestStatus($case_info['epoetry_status']);
      if ($case_info['finished']) {
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_FINISHED);
      }
      $request->save();

      // Make a new draft if needed.
      if ($case_info['create draft']) {
        $node->set('title', 'Basic translation node - update - ' . $case);
        $node->setNewRevision(TRUE);
        $node->save();
      }

      // Moderate the node if needed.
      if (isset($case_info['moderation_state'])) {
        $node = $this->moderateNode($node, $case_info['moderation_state']);
      }

      $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
      $this->clickLink('Remote translations');

      if ($case_info['ongoing']) {
        $this->assertSession()->pageTextContains('Ongoing remote translation request via ePoetry');
      }
      else {
        $this->assertSession()->pageTextNotContains('Ongoing remote translation request via ePoetry');
      }

      if ($case_info['visible']) {
        $this->assertSession()->pageTextContains('Request an update');
        $this->assertSession()->linkExistsExact('Update');

        // If we are on the case where we should see the Update button, assert
        // also that if the request is not active anymore, we don't see it.
        foreach (['Translated', 'Finished', 'Failed'] as $status) {
          $request->setRequestStatus($status);
          $request->save();
        }

        $this->getSession()->reload();
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');

        // Set back the active status for the next iteration.
        $request->setRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REQUESTED);
      }
      else {
        $this->assertSession()->pageTextNotContains('Request an update');
        $this->assertSession()->linkNotExistsExact('Update');
      }

      $node->delete();
    }
  }

}
