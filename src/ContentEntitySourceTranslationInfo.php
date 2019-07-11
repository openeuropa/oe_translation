<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Entity\EntityInterface;
use Drupal\oe_translation\Event\ContentEntitySourceEntityEvent;
use Drupal\tmgmt\JobItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Determines which revision of an entity the translation should go in.
 */
class ContentEntitySourceTranslationInfo implements EntitySourceTranslationInfoInterface {

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
  public function getEntityFromJobItem(JobItemInterface $jobItem): ?EntityInterface {
    $event = new ContentEntitySourceEntityEvent($jobItem);
    $this->eventDispatcher->dispatch(ContentEntitySourceEntityEvent::EVENT, $event);
    return $event->getEntity();
  }

}
