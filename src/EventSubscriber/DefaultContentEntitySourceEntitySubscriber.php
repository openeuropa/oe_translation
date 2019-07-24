<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation\Event\ContentEntitySourceEntityEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Default subscriber to the ContentEntitySourceEntityEvent event.
 */
class DefaultContentEntitySourceEntitySubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * DefaultContentEntitySourceEntitySubscriber constructor.
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
      // We set a low priority because this is the default way to get the
      // entity.
      ContentEntitySourceEntityEvent::EVENT => ['getEntity', -100],
    ];
  }

  /**
   * Sets the correct entity onto the event based on the Job item.
   *
   * By default, we try to load the same revision as was used at the moment
   * of creating the translation job item. However, if one is not set for any
   * reason, we fall back to the default (latest) revision that is loaded.
   *
   * @param \Drupal\oe_translation\Event\ContentEntitySourceEntityEvent $event
   *   The event.
   */
  public function getEntity(ContentEntitySourceEntityEvent $event): void {
    $job_item = $event->getJobItem();
    if (!$job_item->hasField('item_rid') || $job_item->get('item_rid')->isEmpty()) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($job_item->getItemType())->load($job_item->getItemId());
      // Fallback to the default TMGMT behaviour if we can't determine the
      // revision ID.
      if ($entity) {
        // The entity could have been deleted.
        $event->setEntity($entity);
      }

      return;
    }

    // Otherwise, use the revision.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($job_item->getItemType())->loadRevision($job_item->get('item_rid')->value);
    if (!$entity instanceof ContentEntityInterface) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityTypeManager->getStorage($job_item->getItemType())->load($job_item->getItemId());
    }

    if ($entity) {
      // The entity could have been deleted.
      $event->setEntity($entity);
    }
  }

}
