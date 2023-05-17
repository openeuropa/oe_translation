<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Logger;

/**
 * Custom logger channel for Poetry events.
 *
 * This class is used with Drupal 9.
 *
 * @see \Drupal\oe_translation_poetry\Poetry
 */
class PoetryLoggerChannel extends PoetryLoggerChannelBase {

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []) {
    $this->doLog($level, $message, $context);
  }

}
