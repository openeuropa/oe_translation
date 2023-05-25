<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock\Logger;

/**
 * Mock logger to store the logged messages in state.
 *
 * This class is used for Drupal 10.
 */
class MockLoggerDrupal10 extends MockLoggerBase {

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->doLog($level, $message, $context);
  }

}
