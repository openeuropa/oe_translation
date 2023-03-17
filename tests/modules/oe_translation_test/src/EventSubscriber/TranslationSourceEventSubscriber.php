<?php

declare(strict_types=1);

namespace Drupal\oe_translation_test\EventSubscriber;

use Drupal\oe_translation\Event\TranslationSourceEvent;
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
      TranslationSourceEvent::EXTRACT => 'extract',
      TranslationSourceEvent::SAVE => 'save',
    ];
  }

  /**
   * Reacts to the save event.
   *
   * @param \Drupal\oe_translation\Event\TranslationSourceEvent $event
   *   The event.
   */
  public function save(TranslationSourceEvent $event): void {
    $data = $event->getData();
    // Here we just unset the field we set in extract(). It's enough for the
    // test because if we don't unset it, the test will crash due to the
    // field not existing on the node (when it tries to save the value).
    unset($data['oe_translation_test_field']);
    $event->setData($data);
  }

  /**
   * Reacts to the extract event.
   *
   * @param \Drupal\oe_translation\Event\TranslationSourceEvent $event
   *   The event.
   */
  public function extract(TranslationSourceEvent $event): void {
    $data = $event->getData();
    $entity = $event->getEntity();
    if (!isset($entity->setExtraTranslationField)) {
      return;
    }
    $data['oe_translation_test_field'] = [
      '#label' => 'Test field',
      'value' => [
        '#translate' => TRUE,
        '#text' => 'The translatable value',
        '#label' => 'The value',
      ],
    ];

    $event->setData($data);
  }

}
