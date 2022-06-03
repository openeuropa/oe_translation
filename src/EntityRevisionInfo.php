<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_translation\Event\EntityRevisionEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Determines which revision of an entity the translation should go in.
 */
class EntityRevisionInfo implements EntityRevisionInfoInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * ContentEntitySourceTranslationInfo constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher) {
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   *
   *  This is the entity revision the translation should be saved into.
   */
  public function getEntityRevision(ContentEntityInterface $entity, string $target_langcode): ContentEntityInterface {
    // Throw an event to allow other modules to alter the entity revision.
    $event = new EntityRevisionEvent($entity, $target_langcode);
    $this->eventDispatcher->dispatch($event, EntityRevisionEvent::EVENT);
    return $event->getEntity();
  }

}
