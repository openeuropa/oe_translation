<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_translation_epoetry\Event\AvailableLanguagesAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the AvailableLanguagesAlterEvent event.
 */
class AvailableLanguagesSubscriber implements EventSubscriberInterface {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a AvailableLanguagesSubscriber.
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
    return [AvailableLanguagesAlterEvent::NAME => 'alterAvailableLanguages'];
  }

  /**
   * Alters the available ePoetry languages.
   *
   * @param \Drupal\oe_translation_epoetry\Event\AvailableLanguagesAlterEvent $event
   *   The event.
   */
  public function alterAvailableLanguages(AvailableLanguagesAlterEvent $event) {
    $to_remove = $this->state->get('oe_translation_epoetry_test.remove_languages', []);
    $languages = $event->getLanguages();
    foreach ($to_remove as $langcode) {
      unset($languages[$langcode]);
    }

    $event->setLanguages($languages);
  }

}
