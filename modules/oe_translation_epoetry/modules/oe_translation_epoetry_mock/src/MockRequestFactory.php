<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock;

use Drupal\oe_translation_epoetry\RequestFactory;
use OpenEuropa\EPoetry\Authentication\AuthenticationInterface;

/**
 * Overriding the request factory service.
 */
class MockRequestFactory extends RequestFactory {

  /**
   * {@inheritdoc}
   */
  public static function getEpoetryServiceUrl(): ?string {
    $config = \Drupal::config('oe_translation_epoetry_mock.settings');
    $endpoint = $config->get('endpoint');
    return $endpoint && $endpoint !== '' ? $endpoint : parent::getEpoetryServiceUrl();
  }

  /**
   * {@inheritdoc}
   */
  public static function getEpoetryApplicationName(): ?string {
    $config = \Drupal::config('oe_translation_epoetry_mock.settings');
    $application_name = $config->get('application_name');
    return $application_name && $application_name !== '' ? $application_name : parent::getEpoetryApplicationName();
  }

  /**
   * {@inheritdoc}
   */
  protected function getAuthentication(): AuthenticationInterface {
    if (\Drupal::state()->get('oe_translation_epoetry_mock.bypass_mock_authentication', FALSE)) {
      return parent::getAuthentication();
    }
    return new MockAuthentication('ticket');
  }

}
