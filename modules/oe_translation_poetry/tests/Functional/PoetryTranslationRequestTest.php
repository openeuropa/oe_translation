<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_translation_poetry_mock\PoetryMock;
use Drupal\Tests\oe_translation\Functional\TranslationTestBase;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;

/**
 * Tests the requests made to Poetry for translations.
 */
class PoetryTranslationRequestTest extends TranslationTestBase {

  /**
   * The job storage.
   *
   * @var \Drupal\Core\Entity\Sql\SqlEntityStorageInterface
   */
  protected $jobStorage;

  /**
   * The fixture generator.
   *
   * @var \Drupal\oe_translation_poetry_mock\PoetryMockFixturesGenerator
   */
  protected $fixtureGenerator;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_translation_poetry',
    'oe_translation_poetry_mock',
    'oe_translation_poetry_html_formatter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Configure the translator.
    /** @var \Drupal\tmgmt\TranslatorInterface $translator */
    $translator = $this->container->get('entity_type.manager')->getStorage('tmgmt_translator')->load('poetry');
    $translator->setSetting('service_wsdl', PoetryMock::getWsdlUrl());
    $translator->save();

    // Unset some services from the container to force a rebuild.
    $this->container->set('oe_translation_poetry.client.default', NULL);
    $this->container->set('oe_translation_poetry_mock.fixture_generator', NULL);

    $this->jobStorage = $this->entityTypeManager->getStorage('tmgmt_job');
    $this->fixtureGenerator = $this->container->get('oe_translation_poetry_mock.fixture_generator');
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
    $this->drupalGet(Url::fromRoute('oe_translation_poetry.job_queue_checkout'));
    $this->assertSession()->statusCodeEquals(403);

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian', 'cs' => 'Czech']);
    $this->assertSession()->statusCodeEquals(200);

    // Check that two jobs have been created for the two languages and that
    // their status is unprocessed.
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['bg'] = $this->jobStorage->load(1);
    $jobs['cs'] = $this->jobStorage->load(2);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEqual($job->getState(), JobInterface::STATE_UNPROCESSED);
      $this->assertEqual($job->getTargetLangcode(), $lang);
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

    // Make a new request for the same node to check that the version increases.
    $this->createInitialTranslationJobs($node, ['de' => 'German', 'fr' => 'French']);
    $jobs = [];
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs['de'] = $this->jobStorage->load(3);
    $jobs['fr'] = $this->jobStorage->load(4);
    $this->assertCount(2, $jobs);
    foreach ($jobs as $lang => $job) {
      // The jobs should still be unprocessed at this stage.
      $this->assertEqual($job->getState(), JobInterface::STATE_UNPROCESSED);
      $this->assertEqual($job->getTargetLangcode(), $lang);
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
      $this->assertEqual($job->getState(), JobInterface::STATE_UNPROCESSED);
      $this->assertEqual($job->getTargetLangcode(), $lang);
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
      $this->assertEqual($job->getState(), JobInterface::STATE_UNPROCESSED);
      $this->assertEqual($job->getTargetLangcode(), $lang);
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

    // Select some languages to translate.
    $this->createInitialTranslationJobs($node, ['bg' => 'Bulgarian', 'cs' => 'Czech']);

    // Go back to the translation overview to mimic that the user did not
    // finish the translation request.
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->buttonExists('Finish translation request to DGT for Bulgarian, Czech');
    $this->assertCount(2, $this->container->get('oe_translation_poetry.job_queue')->getAllJobs());
    // Delete the first unprocessed job (index 0).
    $this->clickLink('Delete unprocessed job', 0);
    $this->assertSession()->pageTextContains('Are you sure you want to delete the translation job My first node?');
    $this->drupalPostForm(NULL, [], 'Delete');
    // One of the two unprocessed jobs have been deleted.
    $this->assertSession()->pageTextContains('The translation job My first node has been deleted.');
    $this->jobStorage->resetCache();
    $this->assertCount(1, $this->jobStorage->loadMultiple());
    $this->assertCount(1, $this->container->get('oe_translation_poetry.job_queue')->getAllJobs());

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
   * Tests the handling of Poetry status notifications.
   */
  public function testStatusNotifications(): void {
    $this->prepareRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['fr', 'de']);

    // Send a status update accepting the translation.
    $status_notification = $this->fixtureGenerator->statusNotification([
      'code' => 'WEB',
      'part' => '0',
      'version' => '0',
      'product' => 'TRA',
      'number' => 3234,
      'year' => 2010,
    ], 'ONG', [
      [
        'code' => 'FR',
        'date' => '30/08/2019 23:59',
        'accepted_date' => '30/09/2019 23:59',
      ],
      [
        'code' => 'DE',
        'date' => '30/08/2019 23:59',
        'accepted_date' => '30/09/2019 23:59',
      ],
    ]);

    $this->performNotification($status_notification);

    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\Entity\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $this->assertEqual($job->get('poetry_state')->value, 'ongoing');
      $date = $job->get('poetry_request_date_updated')->date;
      $this->assertEqual($date->format('d/m/Y'), '30/09/2019');
      // Two messages are set on the job: date update and marking it as ongoing.
      $this->assertCount(2, $job->getMessages());
    }
  }

  /**
   * Creates jobs that mimic a request having been made to Poetry.
   *
   * @param array $values
   *   The content values.
   * @param array $languages
   *   The job languages.
   */
  protected function prepareRequestedJobs(array $values, array $languages = []): void {
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $node_storage->create([
      'type' => 'page',
    ] + $values);
    $node->save();

    $test_poetry_request_id = [
      'code' => 'WEB',
      'part' => '0',
      'version' => '0',
      'product' => 'TRA',
      'number' => 3234,
      'year' => 2010,
    ];

    // Create a job for French and German to mimic that the request has been
    // made to Poetry.
    foreach ($languages as $language) {
      $job = tmgmt_job_create('en', $language, 0);
      $job->translator = 'poetry';
      $job->addItem('content', 'node', $node->id());
      $job->set('poetry_request_id', $test_poetry_request_id);
      $job->set('state', Job::STATE_ACTIVE);
      $date = new \DateTime('05/04/2019');
      $job->set('poetry_request_date', $date->format('Y-m-d\TH:i:s'));
      $job->save();
    }

    // Ensure the jobs do not contain any info related to Poetry status.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\Entity\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
      $this->assertTrue($job->get('poetry_state')->isEmpty());
    }
  }

  /**
   * Calls the notification endpoint with a message.
   *
   * This mimics notification requests sent by Poetry.
   *
   * @param string $message
   *   The message.
   *
   * @return string
   *   The response XML.
   */
  protected function performNotification(string $message): string {
    $url = Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString();
    $client = new \SoapClient($url . '?wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
    $client->__setCookie('SIMPLETEST_USER_AGENT', drupal_generate_test_ua($this->databasePrefix));
    // @todo test access control on the endpoint.
    return $client->__soapCall('handle', ['admin', 'admin', $message]);
  }

  /**
   * Chooses the languages to translate from the overview page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $languages
   *   The language codes.
   */
  protected function createInitialTranslationJobs(NodeInterface $node, array $languages): void {
    $this->drupalGet($node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Translations of ' . $node->label());

    $values = [];
    foreach (array_keys($languages) as $language) {
      $values["languages[$language]"] = 1;
    }

    $target_languages = count($languages) > 1 ? implode(', ', $languages) : array_shift($languages);
    $expected_title = new FormattableMarkup('Send request to DG Translation for @entity in @target_languages', ['@entity' => $node->label(), '@target_languages' => $target_languages]);

    $this->drupalPostForm($node->toUrl('drupal:content-translation-overview'), $values, 'Request DGT translation for the selected languages');
    $this->assertSession()->pageTextContains($expected_title->__toString());
  }

  /**
   * Submits the translation request on the current page with default values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   */
  protected function submitTranslationRequestForQueue(NodeInterface $node): void {
    // Submit the request form.
    $date = new \DateTime();
    $date->modify('+ 7 days');
    $values = [
      'details[date]' => $date->format('Y-m-d'),
      'details[contact][author]' => 'author name',
      'details[contact][secretary]' => 'secretary name',
      'details[contact][contact]' => 'contact name',
      'details[contact][responsible]' => 'responsible name',
      'details[organisation][responsible]' => 'responsible organisation name',
      'details[organisation][author]' => 'responsible author name',
      'details[organisation][requester]' => 'responsible requester name',
      'details[comment]' => 'Translation comment',
    ];
    $this->drupalPostForm(NULL, $values, 'Send request');
    $this->assertSession()->pageTextContains('The request has been sent to DGT.');
    $this->assertSession()->addressEquals('/en/node/' . $node->id() . '/translations');
  }

  /**
   * Asserts that the given jobs have the correct poetry request ID values.
   *
   * Also ensures that the state is active.
   *
   * @param array $jobs
   *   The jobs.
   * @param array $values
   *   The poetry request ID values.
   */
  protected function assertJobsPoetryRequestIdValues(array $jobs, array $values): void {
    foreach ($jobs as $lang => $job) {
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $this->jobStorage->load($job->id());
      $this->assertEqual($job->getState(), JobInterface::STATE_ACTIVE);
      $this->assertEqual($job->get('poetry_request_id')->first()->getValue(), $values);
    }
  }

}
