<?php

namespace Drupal\oe_translation_poetry\Logger;

use Drupal\Core\Logger\LoggerChannelFactory;

/**
 * Custom logger channel factory for the Poetry client.
 *
 * Since we need a custom logger channel class to process Poetry messages
 * we also need a custom logger channel factory that will instantiate said
 * custom logger when prompted.
 *
 * @see \Drupal\oe_translation_poetry\Poetry
 * @see \Drupal\oe_translation_poetry\Logger\PoetryLoggerChannel
 */
class PoetryLoggerChannelFactory extends LoggerChannelFactory {

  /**
   * {@inheritdoc}
   */
  public function get($channel) {
    if ($channel !== 'poetry') {
      return parent::get($channel);
    }

    if (!isset($this->channels[$channel])) {
      $instance = new PoetryLoggerChannel($channel);

      // If we have a container set the request_stack and current_user services
      // on the channel. It is up to the channel to determine if there is a
      // current request.
      if ($this->container) {
        $instance->setRequestStack($this->container->get('request_stack'));
        $instance->setCurrentUser($this->container->get('current_user'));
      }

      // Pass the loggers to the channel.
      $instance->setLoggers($this->loggers);
      $this->channels[$channel] = $instance;
    }

    return $this->channels[$channel];
  }

}
