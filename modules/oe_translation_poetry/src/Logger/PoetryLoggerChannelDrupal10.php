<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Logger;

/**
 * Custom logger channel for Poetry events.
 *
 * This class is used for Drupal 10.
 *
 * @see \Drupal\oe_translation_poetry\Poetry
 */
class PoetryLoggerChannelDrupal10 extends PoetryLoggerChannelBase {

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $this->doLog($level, $message, $context);
  }

}
