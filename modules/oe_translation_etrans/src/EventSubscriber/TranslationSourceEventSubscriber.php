<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\EventSubscriber;

use Drupal\oe_translation\Event\TranslationSourceEvent;
use Drupal\oe_translation_etrans\TranslationRequestEtrans;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation source events.
 */
class TranslationSourceEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationSourceEvent::SAVE => 'save',
    ];
  }

  /**
   * Reacts to the save event.
   *
   * If the translation of the entity is from an etrans, save onto the entity
   * the flag to indicate this.
   *
   * @param \Drupal\oe_translation\Event\TranslationSourceEvent $event
   *   The event.
   */
  public function save(TranslationSourceEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity->hasField('is_etranslation')) {
      return;
    }

    $data = $event->getData();
    $request = $data['#translation_request'] ?? NULL;
    if (!$request instanceof TranslationRequestEtrans) {
      return;
    }

    $language = $event->getLangcode();
    $translation = $entity->getTranslation($language);
    $translation->set('is_etranslation', TRUE);
  }

}
