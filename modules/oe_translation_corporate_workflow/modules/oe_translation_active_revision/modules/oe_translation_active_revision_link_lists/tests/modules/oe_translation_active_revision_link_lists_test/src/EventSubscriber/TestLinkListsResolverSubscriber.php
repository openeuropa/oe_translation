<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision_link_lists_test\EventSubscriber;

use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to events meant to resolve values in link lists.
 */
class TestLinkListsResolverSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityValueResolverEvent::NAME => ['resolveEntityValues'],
    ];
  }

  /**
   * Sets the non-translatable field value onto the teaser so we can assert it.
   *
   * @param \Drupal\oe_link_lists\Event\EntityValueResolverEvent $event
   *   The event.
   */
  public function resolveEntityValues(EntityValueResolverEvent $event): void {
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() !== 'node') {
      // We only support nodes for the time being.
      return;
    }

    $entity = $event->getEntity();
    if (!$entity->hasField('field_non_translatable_field')) {
      return;
    }

    $link = $event->getLink();
    $non_translatable = $entity->get('field_non_translatable_field')->value;
    $link->setTeaser(['#markup' => '<p>' . $non_translatable . '</p>']);
  }

}
