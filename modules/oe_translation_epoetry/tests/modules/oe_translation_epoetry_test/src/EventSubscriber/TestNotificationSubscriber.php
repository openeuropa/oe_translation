<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry_test\EventSubscriber;

use Drupal\oe_translation_epoetry\Event\EpoetryNotificationRequestUpdateEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRequestedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * A test notification subscriber for the notification request update event.
 */
class TestNotificationSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EpoetryNotificationRequestUpdateEvent::NAME => 'onNotification',
    ];
  }

  /**
   * Subscribes to the notification update event.
   *
   * @param \Drupal\oe_translation_epoetry\Event\EpoetryNotificationRequestUpdateEvent $event
   *   The event.
   */
  public function onNotification(EpoetryNotificationRequestUpdateEvent $event): void {
    if ($event->getNotificationEvent() instanceof StatusChangeRequestedEvent && \Drupal::state()->get('oe_translation_epoetry_test_delay_requested')) {
      sleep(4);
    }
  }

}
