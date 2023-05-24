<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_mock\Logger;

use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for mock logger to store the logged messages in state.
 *
 * Contains all the logic of the logger. The two implementations wrap this class
 * to offer support for the different log() method signature in psr/log 1.x
 * and 3.x (Drupal 9.x and 10.x).
 */
abstract class MockLoggerBase implements LoggerInterface {

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
  protected function doLog($level, string|\Stringable $message, array $context = []): void {
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
