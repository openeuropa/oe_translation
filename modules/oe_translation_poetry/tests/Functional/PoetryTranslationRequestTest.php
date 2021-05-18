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
   * Tests a failed new translation request.
   */
  public function testFailedNewTranslationRequest(): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
      'title' => 'My first node',
    ]);
    $node->save();

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian', 'cs' => 'Czech']);
    $this->assertSession()->statusCodeEquals(200);

    // Mark the mock to return an error.
    $error = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum';
    \Drupal::state()->set('oe_translation_poetry_mock_error', $error);

    $this->submitTranslationRequestForQueue($node, 'There was an error making the request to DGT.');
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $messages = $job->getMessages();
      $message_strings = [];
      foreach ($messages as $message) {
        $message_strings[] = (string) $message->getMessage();
      }

      $this->assertEquals([
        'There were errors with this request: Type: request, Code: -1, Message: ' . $error . '.',
        'The translation job has been rejected by the translation provider.',
      ], $message_strings, sprintf('The messages of the %s job were not correct.', $job->getTargetLangcode()));

      $this->assertEquals(JobInterface::STATE_REJECTED, $job->getState(), sprintf('The state of the %s job was not correct.', $job->getTargetLangcode()));
    }
  }

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
      // The year is the current date year because it's the first request we
      // are making.
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

    // Update the job to make the year different on the saved job to mimic the
    // fact that the previous job was requested in a different year so that we
    // can assert the new requests all follow that year.
    foreach ($jobs as $lang => $job) {
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $this->jobStorage->load($job->id());
      $values = $job->get('poetry_request_id')->first()->getValue();
      $values['year'] = 2020;
      $job->set('poetry_request_id', $values);
      $job->save();
    }

    // Make a new request for the same node to check that the version increases
    // but the year stays the same.
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
      // The year follows the previous job year, even if we are no longer in
      // that year.
      'year' => 2020,
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
      // The year follows the previous job year, even if we are no longer in
      // that year.
      'year' => 2020,
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
      // Since the part has reached 99 and a new number requested, the year
      // can now also take the current one.
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

    $this->resetGlobalIdentifierNumber();

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

  /**
   * Logs in an resets the Poetry global identifier number.
   */
  protected function resetGlobalIdentifierNumber(): void {
    // Augment the translator permissions to be able to configure the
    // translators.
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
    $this->getSession()->getPage()->clickLink('Reset number');
    $this->assertSession()->pageTextContains('Please confirm you want to reset the global identifier number on the next request made to DGT.');
    $this->getSession()->getPage()->pressButton('Confirm');
  }

}
