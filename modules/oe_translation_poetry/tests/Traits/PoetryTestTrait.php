<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Traits;

use Drupal\Core\Url;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\JobInterface;
use EC\Poetry\Messages\Components\Identifier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Contains helper methods for testing the Poetry integration.
 */
trait PoetryTestTrait {

  /**
   * Returns the container.
   *
   * @return \Symfony\Component\DependencyInjection\ContainerInterface
   *   The container.
   */
  protected function getContainer(): ContainerInterface {
    return property_exists($this, 'container') ? $this->container : \Drupal::getContainer();
  }

  /**
   * Calls the notification endpoint with a message.
   *
   * This mimics notification requests sent by Poetry.
   *
   * @param string $message
   *   The message.
   * @param string $path
   *   The notification endpoint path.
   * @param array $credentials
   *   The credentials to use when notifying.
   *
   * @return string
   *   The response XML.
   */
  protected function performNotification(string $message, string $path = '', array $credentials = []): string {
    if (!$credentials) {
      $settings = $this->getContainer()->get('oe_translation_poetry.client.default')->getSettings();
      $credentials['username'] = $settings['notification.username'];
      $credentials['password'] = $settings['notification.password'];
    }

    if ($path === '') {
      $path = Url::fromRoute('oe_translation_poetry.notifications')->setAbsolute()->toString();
    }

    $client = new \SoapClient($path . '?wsdl', ['cache_wsdl' => WSDL_CACHE_NONE]);
    if (property_exists($this, 'databasePrefix')) {
      $client->__setCookie('SIMPLETEST_USER_AGENT', drupal_generate_test_ua($this->databasePrefix));
    }

    return $client->__soapCall('handle', [
      $credentials['username'],
      $credentials['password'],
      $message,
    ]);
  }

  /**
   * Sends a test translation notification for some jobs.
   *
   * @param \Drupal\tmgmt\JobInterface[] $jobs
   *   The jobs.
   * @param string|null $suffix
   *   A string to append to the translation..
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function notifyWithDummyTranslations(array $jobs, string $suffix = NULL): void {
    // Prepare the identifier.
    $identifier = new Identifier();
    $main_job = current($jobs);
    foreach ($main_job->get('poetry_request_id')->first()->getValue() as $name => $value) {
      $identifier->offsetSet($name, $value);
    }
    $main_items = $main_job->getItems();
    $main_item = reset($main_items);

    foreach ($jobs as $job) {
      // Translate the content.
      $items = $job->getItems();
      $item = reset($items);
      $data = $this->getContainer()->get('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => &$info) {
        $info['#text'] .= ' - ' . $job->getTargetLangcode();
        if ($suffix) {
          $info['#text'] .= ' ' . $suffix;
        }
      }

      $translation_notification = $this->getContainer()->get('oe_translation_poetry_mock.fixture_generator')->translationNotification($identifier, $job->getTargetLangcode(), $data, (int) $main_item->id(), (int) $main_job->id());
      $this->performNotification($translation_notification);
    }
  }

  /**
   * Asserts that the jobs in the system have received translation values.
   *
   * @param string|null $suffix
   *   Expected suffix for the translation values.
   */
  protected function assertJobsAreTranslated(string $suffix = NULL): void {
    $this->jobStorage->resetCache();
    $this->entityTypeManager->getStorage('tmgmt_job_item')->resetCache();
    $jobs = $this->jobStorage->loadMultiple();
    foreach ($jobs as $job) {
      $this->assertEquals(Job::STATE_ACTIVE, $job->getState());
      $this->assertEquals($job->get('poetry_state')->value, PoetryTranslator::POETRY_STATUS_TRANSLATED);

      $items = $job->getItems();
      $item = reset($items);
      $data = $this->container->get('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => $info) {
        $this->assertNotEmpty($info['#translation']);
        $expected_translation = $suffix ? ($info['#text'] . ' - ' . $job->getTargetLangcode() . ' ' . $suffix) : ($info['#text'] . ' - ' . $job->getTargetLangcode());
        $this->assertEquals($expected_translation, $info['#translation']['#text']);
      }
    }
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
      $this->assertEquals(JobInterface::STATE_ACTIVE, $job->getState());
      $this->assertEquals($values, $job->get('poetry_request_id')->first()->getValue());
    }
  }

}
