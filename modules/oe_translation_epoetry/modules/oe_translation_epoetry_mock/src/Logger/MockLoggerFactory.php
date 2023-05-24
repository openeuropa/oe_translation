<?php

namespace Drupal\oe_translation_epoetry_mock\Logger;

use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory class for mock loggers.
 *
 * Creates the correct instance based on the Drupal major version.
 */
class MockLoggerFactory {

  /**
   * Creates an instance of mock logger.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger instance.
   */
  public static function create(StateInterface $state): LoggerInterface {
    return version_compare(\Drupal::VERSION, '10.0.0') >= 0
      ? new MockLoggerDrupal10($state)
      : new MockLogger($state);
  }

}
