<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Url;
use Drupal\tmgmt\JobInterface;

/**
 * Tests the requests made to Poetry for translations.
 */
class PoetryTranslationRequestTest extends PoetryTranslationTestBase {

  /**
   * Tests new translation requests.
   */
  public function testNewTranslationRequest(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My first node',
    ]);
    $node->save();

    // Assert we can't access the checkout route without jobs in the queue.
    $this->drupalGet(Url::fromRoute('oe_translation_poetry.job_queue_checkout_new', ['node' => $node->id()]));
    $this->assertSession()->statusCodeEquals(403);

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian', 'cs' => 'Czech']);
    $this->assertSession()->statusCodeEquals(200);

    // Check that two jobs have been created for the two languages and that
    // their status is unprocessed.
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(1);
    $jobs['cs'] = $this->jobStorage->load(2);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEquals(JobInterface::STATE_UNPROCESSED, $job->getState());
      $this->assertEquals($lang, $job->getTargetLangcode());
    }

    // Submit the request to Poetry for the two jobs.
    $this->submitTranslationRequestForQueue($node);
    $this->jobStorage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      // The number is the first number because it's the first request we are
      // making.
      'number' => '1000',
      // We always start with version and part 0 in the first request.
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);

    // Abort jobs to have the request button displayed again.
    $this->abort($jobs);

    // Make a new request for the same node to check that the version increases.
    $this->createInitialTranslationJobs($node, ['de' => 'German', 'fr' => 'French']);
    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['de'] = $this->jobStorage->load(3);
    $jobs['fr'] = $this->jobStorage->load(4);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEquals(JobInterface::STATE_UNPROCESSED, $job->getState());
      $this->assertEquals($lang, $job->getTargetLangcode());
    }

    // Submit the request to Poetry for the two new jobs in the queue.
    $this->submitTranslationRequestForQueue($node);
    $this->jobStorage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1000',
      // The version should increase.
      'version' => '1',
      // The part should stay the same.
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);

    // Create a new node to increase the part and reset the version for that
    // number.
    /** @var \Drupal\node\NodeInterface $node_two */
    $node_two = $node_storage->create([
      'type' => 'page',
      'title' => 'My second node',
    ]);
    $node_two->save();

    // Abort jobs to have the request button displayed again.
    $this->abort($jobs);

    $this->createInitialTranslationJobs($node_two, ['bg' => 'Bulgarian', 'cs' => 'Czech']);
    // Check that two jobs have been created for the two languages and that
    // their status is unprocessed.
    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(5);
    $jobs['cs'] = $this->jobStorage->load(6);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEquals(JobInterface::STATE_UNPROCESSED, $job->getState());
      $this->assertEquals($lang, $job->getTargetLangcode());
    }

    // Submit the request to Poetry for the two jobs.
    $this->submitTranslationRequestForQueue($node_two);
    $this->jobStorage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1000',
      // Version is reset to 0 because it's a new node.
      'version' => '0',
      // The part increases because it's a request for a new node.
      'part' => '1',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);

    // Update programmatically the part of the last two jobs to 99 to mimic
    // that the Poetry service needs to give us a new number.
    $fake_poetry_request_id = [
      'part' => '99',
    ] + $expected_poetry_request_id;

    foreach ($jobs as $job) {
      $job->set('poetry_request_id', $fake_poetry_request_id);
      $job->save();
    }

    // Create a new node which would require an increment in the part.
    /** @var \Drupal\node\NodeInterface $node_three */
    $node_three = $node_storage->create([
      'type' => 'page',
      'title' => 'My third node',
    ]);
    $node_three->save();

    // Abort jobs to have the request button displayed again.
    $this->abort($jobs);

    $this->createInitialTranslationJobs($node_three, ['bg' => 'Bulgarian', 'cs' => 'Czech']);
    // Check that two jobs have been created for the two languages and that
    // their status is unprocessed.
    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(7);
    $jobs['cs'] = $this->jobStorage->load(8);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEquals(JobInterface::STATE_UNPROCESSED, $job->getState());
      $this->assertEquals($lang, $job->getTargetLangcode());
    }

    // Submit the request to Poetry for the two jobs.
    $this->submitTranslationRequestForQueue($node_three);
    $this->jobStorage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1001',
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);
  }

  /**
   * Tests that we can clear or finalize unprocessed jobs.
   */
  public function testUnprocessedJobHandling(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My first node',
    ]);
    $node->save();
    $second_node = $node_storage->create([
      'type' => 'page',
      'title' => 'My second node',
    ]);
    $second_node->save();

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian', 'cs' => 'Czech']);

    // Assert that the second node doesn't have any pending jobs.
    $this->drupalGet($second_node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonExists('Request a DGT translation for the selected languages');
    // Assert that each node has its own queue by trying to access the checkout
    // of the second node (which has no pending jobs).
    $this->drupalGet(Url::fromRoute('oe_translation_poetry.job_queue_checkout_new', ['node' => $second_node->id()]));
    $this->assertSession()->statusCodeEquals(403);

    // Go back to the translation overview to mimic that the user did not
    // finish the translation request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonExists('Finish translation request to DGT for Bulgarian, Czech');
    $this->assertCount(2, $this->container->get('oe_translation_poetry.job_queue_factory')->get($node)->getAllJobs());
    // Delete the first unprocessed job (index 0).
    $this->clickLink('Delete unprocessed job', 0);
    $this->assertSession()->pageTextContains('Are you sure you want to delete the translation job My first node?');
    $this->drupalPostForm(NULL, [], 'Delete');
    // One of the two unprocessed jobs have been deleted.
    $this->assertSession()->pageTextContains('The translation job My first node has been deleted.');
    $this->jobStorage->resetCache();
    $this->assertCount(1, $this->jobStorage->loadMultiple());
    $this->assertCount(1, $this->container->get('oe_translation_poetry.job_queue_factory')->get($node)->getAllJobs());

    // Finalize the translation for remaining unprocessed job.
    $this->drupalPostForm(NULL, [], 'Finish translation request to DGT for Czech');
    $this->submitTranslationRequestForQueue($node);
    $this->jobStorage->resetCache();

    // The jobs should have gotten submitted and the identification numbers
    // set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1000',
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];

    $this->assertJobsPoetryRequestIdValues($this->jobStorage->loadMultiple(), $expected_poetry_request_id);
  }

  /**
   * Tests we can reset the global identifying number.
   */
  public function testResetGlobalIdentifierNumberRequest(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My first node',
    ]);
    $node->save();

    // Select a language to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian']);

    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(1);
    // Submit the request to Poetry.
    $this->submitTranslationRequestForQueue($node);
    $this->jobStorage->resetCache();

    // The job should have gotten submitted and the identification number set.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      // The number is the first number because it's the first request we are
      // making.
      'number' => '1000',
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);
    // Abort jobs to have the request button displayed again.
    $this->abort($jobs);

    // Create a new node to force a new number to be generated.
    /** @var \Drupal\node\NodeInterface $node_two */
    $node_two = $node_storage->create([
      'type' => 'page',
      'title' => 'My second node',
    ]);
    $node_two->save();

    // Login with a user that can edit the Poetry translator.
    /** @var \Drupal\user\RoleInterface $role */
    $role = $this->entityTypeManager->getStorage('user_role')->load('oe_translator');
    $permissions = $role->getPermissions();
    $permissions[] = 'administer menu';
    $permissions[] = 'administer tmgmt';
    $user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($user);

    // Force the use of the sequence to get a new number.
    $translator_storage = $this->entityTypeManager->getStorage('tmgmt_translator');
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $translator_storage->load('poetry');
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->getSession()->getPage()->pressButton('Reset number');
    $this->assertSession()->pageTextContains('Please confirm you want to reset the global identifier number on the next request made to DGT.');
    $this->getSession()->getPage()->pressButton('Confirm');

    $this->createInitialTranslationJobs($node_two, ['bg' => 'Bulgarian']);

    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(2);

    $this->submitTranslationRequestForQueue($node_two);
    $this->jobStorage->resetCache();

    // The job should have gotten submitted and the identification number
    // should have a new number instead of increasing the part.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1001',
      'version' => '0',
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);

    // Flag the number to be reset and cancel it afterwards to ensure
    // we can continue the process without resetting.
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->assertSession()->pageTextContains('WARNING: Resetting the global identifier number must only be done under extreme circumstances and only after confirming it with DGT.');
    $this->assertSession()->pageTextNotContains('WARNING: The next request will reset the global identifier number.');
    $this->getSession()->getPage()->pressButton('Reset number');
    $this->assertSession()->pageTextContains('Please confirm you want to reset the global identifier number on the next request made to DGT.');
    $this->getSession()->getPage()->pressButton('Confirm');
    $this->drupalGet($translator->toUrl('edit-form'));
    $this->assertSession()->pageTextNotContains('WARNING: Resetting the global identifier number must only be done under extreme circumstances and only after confirming it with DGT.');
    $this->assertSession()->pageTextContains('WARNING: The next request will reset the global identifier number.');
    $this->getSession()->getPage()->pressButton('Cancel reset');

    // Create a new node to ensure new requests use the new number.
    /** @var \Drupal\node\NodeInterface $node_three */
    $node_three = $node_storage->create([
      'type' => 'page',
      'title' => 'My third node',
    ]);
    $node_three->save();

    $this->createInitialTranslationJobs($node_three, ['bg' => 'Bulgarian']);

    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(3);

    $this->submitTranslationRequestForQueue($node_three);
    $this->jobStorage->resetCache();

    // The job should have been submitted and the part should have increased.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1001',
      'version' => '0',
      'part' => '1',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);

    // Make a new request for the first node to check that the version
    // increases and the original number is kept.
    $this->createInitialTranslationJobs($node, ['de' => 'German']);
    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['de'] = $this->jobStorage->load(4);

    // Submit the request to Poetry for the new job.
    $this->submitTranslationRequestForQueue($node);
    $this->jobStorage->resetCache();

    // The job should have been submitted and the old identification number
    // should have been kept.
    $expected_poetry_request_id = [
      'code' => 'WEB',
      'year' => date('Y'),
      'number' => '1000',
      // The version should increase.
      'version' => '1',
      // The part should stay the same.
      'part' => '0',
      'product' => 'TRA',
    ];
    $this->assertJobsPoetryRequestIdValues($jobs, $expected_poetry_request_id);
  }

  /**
   * Abort jobs.
   *
   * @param \Drupal\tmgmt\JobInterface[] $jobs
   *   The jobs to abort.
   */
  protected function abort(array $jobs): void {
    foreach ($jobs as $job) {
      $fullJob = $this->jobStorage->load($job->id());
      $fullJob->aborted();
    }
  }

}
