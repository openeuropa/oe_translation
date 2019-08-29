<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Functional;

use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;
use EC\Poetry\Messages\Components\Identifier;

/**
 * Tests the Poetry notifications.
 */
class PoetryNotificationTest extends PoetryTranslationTestBase {

  /**
   * Tests the handling of Poetry status notifications.
   */
  public function testStatusNotifications(): void {
    $this->prepareRequestedJobs([
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

    $this->performNotification($status_notification);
    // Check that the jobs have been correctly updated.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());

    foreach ($jobs as $langcode => $job) {
      if ($langcode === 'it') {
        // Italian was refused.
        $this->assertTrue($job->get('poetry_state')->isEmpty());
        $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
        $this->assertEqual($job->getState(), Job::STATE_REJECTED);
        $this->assertCount(1, $job->getMessages());
        continue;
      }

      // The others were accepted.
      $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_ONGOING);
      $date = $job->get('poetry_request_date_updated')->date;
      $this->assertEqual($date->format('d/m/Y'), '30/09/2019');
      $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
      // Two messages are set on the job: date update and marking it as ongoing.
      $this->assertCount(2, $job->getMessages());
    }

    // Send a notification cancelling one of the languages that were accepted.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG', [], [], [
      [
        'code' => 'DE',
      ],
    ]);

    $this->performNotification($status_notification);

    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
    $jobs = $this->indexJobsByLanguage($this->jobStorage->loadMultiple());
    foreach ($jobs as $langcode => $job) {
      if ($langcode === 'fr') {
        // French remained accepted.
        $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_ONGOING);
        $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
        continue;
      }

      // The others were cancelled.
      $this->assertTrue($job->get('poetry_state')->isEmpty());
      $this->assertTrue($job->get('poetry_request_date_updated')->isEmpty());
      $this->assertEqual($job->getState(), Job::STATE_REJECTED);
    }
  }

  /**
   * Tests the handling of Poetry translation notifications.
   */
  public function testTranslationNotifications(): void {
    $this->prepareRequestedJobs([
      'title' => 'My node title',
      'field_oe_demo_translatable_body' => 'My node body',
    ], ['fr']);

    $identifier = new Identifier();
    foreach ($this->defaultIdentifierInfo as $name => $value) {
      $identifier->offsetSet($name, $value);
    }

    // Accept the translations.
    $status_notification = $this->fixtureGenerator->statusNotification($this->defaultIdentifierInfo, 'ONG',
      [
        [
          'code' => 'FR',
          'date' => '30/08/2019 23:59',
          'accepted_date' => '30/09/2019 23:59',
        ],
      ]);

    $this->performNotification($status_notification);

    // Send the translations for each job.
    $jobs = $this->jobStorage->loadMultiple();
    $job = reset($jobs);
    $items = $job->getItems();
    $item = reset($items);
    $data = $this->container->get('tmgmt.data')->filterTranslatable($item->getData());
    foreach ($data as $field => &$info) {
      $info['#text'] .= ' - ' . $job->getTargetLangcode();
    }

    $translation_notification = $this->fixtureGenerator->translationNotification($identifier, $job->getTargetLangcode(), $data, (int) $item->id(), (int) $job->id());
    $this->performNotification($translation_notification);

    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    $jobs = $this->jobStorage->loadMultiple();
    $job = reset($jobs);
    $this->assertEqual($job->getState(), Job::STATE_ACTIVE);
    $this->assertEqual($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_TRANSLATED);

    $items = $job->getItems();
    $item = reset($items);
    $data = $this->container->get('tmgmt.data')->filterTranslatable($item->getData());
    foreach ($data as $field => $info) {
      $this->assertNotEmpty($info['#translation']);
      $this->assertEqual($info['#translation']['#text'], $info['#text'] . ' - ' . $job->getTargetLangcode());
    }
  }

  /**
   * Tests the access on the notification endpoint.
   */
  public function testNotificationEndpointAccess(): void {
    $this->prepareRequestedJobs([
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
    $response = $this->performNotification($status_notification, $credentials);
    $xml = simplexml_load_string($response);
    $this->assertEqual((string) $xml->request->status->statusMessage, 'Poetry service cannot authenticate on notification callback: username or password not valid.');

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

    // Create a job for French and German to mimic that the request has been
    // made to Poetry.
    foreach ($languages as $language) {
      $job = tmgmt_job_create('en', $language, 0);
      $job->translator = 'poetry';
      $job->addItem('content', 'node', $node->id());
      $job->set('poetry_request_id', $this->defaultIdentifierInfo);
      $job->set('state', Job::STATE_ACTIVE);
      $date = new \DateTime('05/04/2019');
      $job->set('poetry_request_date', $date->format('Y-m-d\TH:i:s'));
      $job->save();
    }

    // Ensure the jobs do not contain any info related to Poetry status.
    $this->jobStorage->resetCache();
    /** @var \Drupal\tmgmt\JobInterface[] $jobs */
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
   * @param array $credentials
   *   The endpoint credentials.
   *
   * @return string
   *   The response XML.
   */
  protected function performNotification(string $message, array $credentials = []): string {
    if (!$credentials) {
      $settings = $this->container->get('oe_translation_poetry.client.default')->getSettings();
      $credentials['username'] = $settings['notification.username'];
      $credentials['password'] = $settings['notification.password'];
    }

    $url = Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString();
    $client = new \SoapClient($url . '?wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
    $client->__setCookie('SIMPLETEST_USER_AGENT', drupal_generate_test_ua($this->databasePrefix));
    return $client->__soapCall('handle', [
      $credentials['username'],
      $credentials['password'],
      $message,
    ]);
  }

}
