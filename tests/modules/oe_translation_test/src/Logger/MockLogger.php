<?php

declare(strict_types=1);

namespace Drupal\oe_translation_test\Logger;

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
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->doLog($level, $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  protected function doLog($level, string|\Stringable $message, array $context = []): void {
    if (!str_contains($context['channel'], 'oe_translation_')) {
      return;
    }
    $logs = $this->state->get('oe_translation_mock_logs', []);
    $logs[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
    $this->state->set('oe_translation_mock_logs', $logs);
  }

  /**
   * Returns all the logs.
   *
   * @return array
   *   The logs.
   */
  public function getLogs(): array {
    $this->state->resetCache();
    return $this->state->get('oe_translation_mock_logs', []);
  }

  /**
   * Clears all the logs.
   */
  public function clearLogs(): void {
    $this->state->set('oe_translation_mock_logs', []);
  }

}
