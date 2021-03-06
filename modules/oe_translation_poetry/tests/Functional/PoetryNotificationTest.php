<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobItemInterface;

/**
 * Tests the Poetry notifications.
 */
class PoetryNotificationTest extends PoetryTranslationTestBase {

  /**
   * Tests the handling of Poetry status notifications.
   */
  public function testStatusNotifications(): void {
    $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['fr', 'pt-pt', 'it']);

    // Send a status update accepting the translation for two languages but
    // refusing for one.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'FR',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
        [
          'code' => 'PT',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
      ],
      [
        [
          'code' => 'IT',
        ],
      ]);

    $this->performNotification($status_notification);
    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());

    foreach ($jobs as $langcode => $job) {
      if ($langcode === 'it') {
        // Italian was refused.
        $this->assertEquals(PoetryTranslator::POETRY_STATUS_CANCELLED, $job->get('poetry_state')->value);
        $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
        $this->assertEquals(Job::STATE_ABORTED, $job->getState());
        $this->assertCount(1, $job->getMessages());

        $job_item = current($job->getItems());
        $this->assertEquals(JobItemInterface::STATE_ABORTED, $job_item->get('state')->value);
        continue;
      }

      // The others were accepted.
      $this->assertEquals(PoetryTranslator::POETRY_STATUS_ONGOING, $job->get('poetry_state')->value);
      $date = $job->get('poetry_request_date_updated')->date;
      $this->assertEquals('30/09/2019', $date->format('d/m/Y'));
      $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
      // Two messages are set on the job: date update and marking it as ongoing.
      $this->assertCount(2, $job->getMessages());
    }

    // Send a notification cancelling one of the languages that were accepted.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG', [], [], [
      [
        'code' => 'PT',
      ],
    ]);

    $this->performNotification($status_notification);

    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $langcode => $job) {
      if ($langcode === 'fr') {
        // French remained accepted.
        $this->assertEquals(PoetryTranslator::POETRY_STATUS_ONGOING, $job->get('poetry_state')->value);
        $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
        continue;
      }

      // The others were cancelled.
      $this->assertEquals(PoetryTranslator::POETRY_STATUS_CANCELLED, $job->get('poetry_state')->value);
      $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
      $this->assertEquals(Job::STATE_ABORTED, $job->getState());
    }
  }

  /**
   * Tests the handling of Poetry translation notifications.
   */
  public function testTranslationNotifications(): void {
    $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['fr', 'es']);

    // Accept the translations.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'FR',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
        [
          'code' => 'ES',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
      ]);

    $this->performNotification($status_notification);

    // Send the translations for each job.
    $jobs = $this->jobStorage->loadMultiple();
    $this->notifyWithDummyTranslations($jobs);
    $this->assertJobsAreTranslated();

    // Send a translation update before the translation gets accepted.
    $this->notifyWithDummyTranslations($jobs, 'UPDATED');
    $this->assertJobsAreTranslated('UPDATED');
  }

  /**
   * Tests the access on the notification endpoint.
   */
  public function testNotificationEndpointAccess(): void {
    $this->createNodeWithRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['fr', 'de', 'it']);

    // Send a status update accepting the translation for two languages but
    // refusing for one.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
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
      ],
      [
        [
          'code' => 'IT',
        ],
      ]);

    $credentials = [
      'username' => 'dummy',
      'password' => 'incorrect',
    ];
    $response = $this->performNotification($status_notification, '', $credentials);
    $xml = simplexml_load_string($response);
    $this->assertEquals('Poetry service cannot authenticate on notification callback: username or password not valid.', (string) $xml->request->status->statusMessage);

    // Load the jobs and assert that nothing happened because the access was
    // denied.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
      $this->assertTrue($job->get('poetry_state')->isEmpty());
    }
  }

}
