<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Logger;

use Drupal\Core\Logger\LoggerChannel;
use EC\Poetry\Events\Client\ClientRequestEvent;
use EC\Poetry\Events\Client\ClientResponseEvent;
use EC\Poetry\Events\ExceptionEvent;
use EC\Poetry\Events\NotificationHandler\ReceivedNotificationEvent;
use EC\Poetry\Events\Notifications\StatusUpdatedEvent;
use EC\Poetry\Events\Notifications\TranslationReceivedEvent;
use EC\Poetry\Events\ParseNotificationEvent;
use EC\Poetry\Events\ParseResponseEvent;

/**
 * Base class for Poetry custom logger channel.
 *
 * Contains all the logic of the logger. The two implementations wrap this class
 * to offer support for the different log() method signature in psr/log 1.x
 * and 3.x (Drupal 9.x and 10.x).
 *
 * @see \Drupal\oe_translation_poetry\Poetry
 */
abstract class PoetryLoggerChannelBase extends LoggerChannel {

  /**
   * Executes the \Psr\Log\LoggerInterface::log() logic.
   *
   * @param mixed $level
   *   The log level.
   * @param string|\Stringable $message
   *   The message.
   * @param array $context
   *   The context.
   */
  protected function doLog($level, string|\Stringable $message, array $context = []): void {
    if (!$this->isPoetryEvent($message)) {
      parent::log($level, $message, $context);
    }

    // Construct a new message for Poetry events.
    $string = 'Poetry event <strong>@name</strong>: <br /><br />';
    $context['@name'] = $message;
    if (isset($context['username'])) {
      $string .= 'Username: <strong>@username</strong> <br /><br />';
      $context['@username'] = $context['username'];
    }
    if (isset($context['password'])) {
      $string .= 'Password: <strong>@password</strong> \n\n';
      $context['@password'] = $context['password'];
    }
    if (isset($context['message'])) {
      $string .= '<pre>@message</pre>';
      $context['@message'] = var_export($context['message'], TRUE);
    }

    parent::log($level, $string, $context);
  }

  /**
   * Checks if the logged message is from a Poetry event.
   *
   * @param string $message
   *   The message name, i.e. the event name.
   *
   * @return bool
   *   Whether it's a Poetry event log.
   */
  protected function isPoetryEvent(string $message): bool {
    $events = [
      ParseResponseEvent::NAME,
      ParseNotificationEvent::NAME,
      StatusUpdatedEvent::NAME,
      TranslationReceivedEvent::NAME,
      ClientRequestEvent::NAME,
      ClientResponseEvent::NAME,
      ReceivedNotificationEvent::NAME,
      ExceptionEvent::NAME,
    ];

    return in_array($message, $events);
  }

}
