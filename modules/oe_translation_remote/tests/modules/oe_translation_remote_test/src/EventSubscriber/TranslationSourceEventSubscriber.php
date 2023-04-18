<?php

declare(strict_types=1);

namespace Drupal\oe_translation_remote_test\EventSubscriber;

use Drupal\node\NodeInterface;
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
    // Unset the field so there is no attempt to save it onto the node.
    unset($data['oe_translation_test_field']);
    $node = $event->getEntity();

    // Get the #fake_value from the original data and set it onto a node title
    // so we can assert it in the test.
    if ($node instanceof NodeInterface) {
      $node->set('title', $node->label() . ' ' . $event->getOriginalData()['oe_translation_test_field']['#fake_value']);
    }

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

    // Create a new field that also has some metadata that would not normally
    // exist on an incoming remote translation file.
    $data['oe_translation_test_field'] = [
      '#label' => 'Test field',
      '#fake_value' => 'test fake value',
      'value' => [
        '#translate' => TRUE,
        '#text' => 'The translatable value',
        '#label' => 'The value',
      ],
    ];

    $event->setData($data);
  }

}
