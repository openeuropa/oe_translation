<?php

namespace Drupal\oe_translation_epoetry_mock\Logger;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Mock logger to store the logged messages in state.
 */
class MockLogger implements LoggerInterface {

  use RfcLoggerTrait;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a MockLogger.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    if ($context['channel'] !== 'oe_translation_epoetry') {
      return;
    }
    $logs = $this->state->get('oe_translation_epoetry_mock_logs', []);
    $logs[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
    $this->state->set('oe_translation_epoetry_mock_logs', $logs);
  }

  /**
   * Returns all the logs.
   *
   * @return array
   *   The logs.
   */
  public static function getLogs(): array {
    \Drupal::state()->resetCache();
    return \Drupal::state()->get('oe_translation_epoetry_mock_logs', []);
  }

  /**
   * Clears all the logs.
   */
  public static function clearLogs(): void {
    \Drupal::state()->set('oe_translation_epoetry_mock_logs', []);
  }

}
