<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_epoetry\Event\OnBehalfOfEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the onBehalfOf event.
 */
class OnBehalfOfSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a OnBehalfOfSubscriber.
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
  public static function getSubscribedEvents() {
    return [OnBehalfOfEvent::NAME => 'getOnBehalfOf'];
  }

  /**
   * Sets a test onBehalfOf value.
   *
   * @param \Drupal\oe_translation_epoetry\Event\OnBehalfOfEvent $event
   *   The event.
   */
  public function getOnBehalfOf(OnBehalfOfEvent $event): void {
    if ($this->state->get('oe_translation_epoetry_test.on_behalf')) {
      $event->setValue($this->state->get('oe_translation_epoetry_test.on_behalf'));
    }
  }

}
