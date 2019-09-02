<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_translation_poetry\Traits;

use Drupal\Core\Url;
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
   */
  protected function notifyWithDummyTranslations(array $jobs): void {
    foreach ($jobs as $job) {
      // Prepare the identifier.
      $identifier = new Identifier();
      foreach ($job->get('poetry_request_id')->first()->getValue() as $name => $value) {
        $identifier->offsetSet($name, $value);
      }

      // Translate the content.
      $items = $job->getItems();
      $item = reset($items);
      $data = $this->getContainer()->get('tmgmt.data')->filterTranslatable($item->getData());
      foreach ($data as $field => &$info) {
        $info['#text'] .= ' - ' . $job->getTargetLangcode();
      }

      $translation_notification = $this->getContainer()->get('oe_translation_poetry_mock.fixture_generator')->translationNotification($identifier, $job->getTargetLangcode(), $data, (int) $item->id(), (int) $job->id());
      $this->performNotification($translation_notification);
    }
  }

}
