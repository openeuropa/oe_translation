<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_mock\Logger;

/**
 * Mock logger to store the logged messages in state.
 *
 * This class is used for Drupal 9.
 */
class MockLogger extends MockLoggerBase {

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->doLog($level, $message, $context);
  }

}
