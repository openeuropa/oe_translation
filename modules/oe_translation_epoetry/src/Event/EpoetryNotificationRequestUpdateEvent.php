<?php

declare(strict_types=1);

namespace Drupal\oe_translation_epoetry\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use OpenEuropa\EPoetry\Notification\Event\BaseNotificationEvent;

/**
 * Event thrown when updating a translation request as part of a notification.
 */
class EpoetryNotificationRequestUpdateEvent extends Event {

  /**
   * The event name.
   */
  const NAME = 'oe_translation_epoetry_notification_update_event';

  /**
   * The local translation request.
   *
   * @var \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   */
  protected $translationRequest;

  /**
   * The notification event.
   *
   * @var \OpenEuropa\EPoetry\Notification\Event\BaseNotificationEvent
   */
  protected $event;

  /**
   * Constructs a EpoetryNotificationRequestUpdateEvent.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   * @param \OpenEuropa\EPoetry\Notification\Event\BaseNotificationEvent $event
   *   The notification event.
   */
  public function __construct(TranslationRequestEpoetryInterface $translation_request, BaseNotificationEvent $event) {
    $this->translationRequest = $translation_request;
    $this->event = $event;
  }

  /**
   * Returns the translation request.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface
   *   The translation request.
   */
  public function getTranslationRequest(): TranslationRequestEpoetryInterface {
    return $this->translationRequest;
  }

  /**
   * Returns the event.
   *
   * @return \OpenEuropa\EPoetry\Notification\Event\BaseNotificationEvent
   *   The notification event.
   */
  public function getNotificationEvent(): BaseNotificationEvent {
    return $this->event;
  }

}
