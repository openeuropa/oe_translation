<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use EC\Poetry\Events\Notifications\StatusUpdatedEvent;
use EC\Poetry\Events\Notifications\TranslationReceivedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Poetry notification subscriber.
 *
 * This is not a typical Drupal event subscriber but one that is manually
 * registered in the Poetry library event dispatcher.
 */
class PoetryNotificationSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a PoetryNotificationSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationReceivedEvent::NAME => 'onTranslationReceivedEvent',
      StatusUpdatedEvent::NAME => 'onStatusUpdatedEvent',
    ];
  }

  /**
   * Notification handler for when a translation is received.
   *
   * @param \EC\Poetry\Events\Notifications\TranslationReceivedEvent $event
   *   Event object.
   */
  public function onTranslationReceivedEvent(TranslationReceivedEvent $event): void {

  }

  /**
   * Notification handler for when a status message arrives.
   *
   * @param \EC\Poetry\Events\Notifications\StatusUpdatedEvent $event
   *   Event object.
   */
  public function onStatusUpdatedEvent(StatusUpdatedEvent $event): void {

  }

}
