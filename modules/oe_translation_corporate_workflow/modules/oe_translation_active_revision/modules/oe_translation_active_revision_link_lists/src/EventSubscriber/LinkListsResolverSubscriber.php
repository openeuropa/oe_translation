<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_active_revision_link_lists\EventSubscriber;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to events meant to resolve values in link lists.
 */
class LinkListsResolverSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a LinkListsResolverSubscriber.
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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EntityValueResolverEvent::NAME => ['resolveEntityValues', 1000],
    ];
  }

  /**
   * Sets the correct active entity revision onto the event.
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
    if ($entity->isDefaultTranslation()) {
      // If we are on the source translation page, we don't have to do anything.
      return;
    }

    $language = $entity->language()->getId();

    $cache = new CacheableMetadata();
    /** @var \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping */
    $mapping = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getLangcodeMapping($language, $entity, $cache);
    $event->addCacheableDependency($cache);
    if (!$mapping->isMapped()) {
      return;
    }

    if ($mapping->isMappedToNull()) {
      // If it's mapped to NULL, we need to show the original EN version.
      $event->setEntity($entity->getUntranslated());
      return;
    }

    $revision = $mapping->getEntity();
    $translation = $revision->getTranslation($language);
    $event->setEntity($translation);
  }

}
