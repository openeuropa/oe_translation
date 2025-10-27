<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_translation\EntityRevisionInfoInterface;
use Drupal\oe_translation\Event\TranslationSynchronisationEvent;
use Drupal\oe_translation_active_revision\Plugin\Field\FieldType\LanguageWithEntityRevisionItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation synchronisation event.
 */
class TranslationSynchronisationSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity revision info service.
   *
   * @var \Drupal\oe_translation\EntityRevisionInfoInterface
   */
  protected $entityRevisionInfo;

  /**
   * Constructs a new TranslationSynchronisationSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation\EntityRevisionInfoInterface $entityRevisionInfo
   *   The entity revision info service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityRevisionInfoInterface $entityRevisionInfo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityRevisionInfo = $entityRevisionInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [TranslationSynchronisationEvent::NAME => 'onSync'];
  }

  /**
   * Reacts to when a translation get synchronised.
   *
   * Removes mappings for this language.
   *
   * @param \Drupal\oe_translation\Event\TranslationSynchronisationEvent $event
   *   The event.
   */
  public function onSync(TranslationSynchronisationEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof NodeInterface) {
      // We only support nodes for the time being.
      return;
    }
    // We need to make sure we are using the entity onto which the sync would
    // take place.
    $entity = $this->entityRevisionInfo->getEntityRevision($entity, $event->getLangcode());

    // Get the mapping.
    /** @var \Drupal\oe_translation_active_revision\LanguageRevisionMapping $mapping */
    $mapping = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getLangcodeMapping($event->getLangcode(), $entity, new CacheableMetadata());
    if (!$mapping->isMapped() && !$mapping->isMappedToNull()) {
      // If there is no kind of mapping, we don't have to do anything.
      return;
    }

    // At this point, it means we have at either a mapping to a revision or
    // to null.
    /** @var \Drupal\oe_translation_active_revision\ActiveRevisionInterface $active_revision */
    $active_revision = $this->entityTypeManager->getStorage('oe_translation_active_revision')->getActiveRevisionForEntity($entity->getEntityTypeId(), $entity->id());

    // Check if the revision being synced to is the validated or published one.
    $moderation_state = $entity->get('moderation_state')->value;
    if ($moderation_state === 'validated') {
      // If the translation is synced on the major version that is validated,
      // we have to change the scope of the mapping "published" only. This is
      // because the validated version just got its new translation so it no
      // longer needs to stay mapped to the previous version.
      $active_revision->updateMappingScope($event->getLangcode(), $entity->getEntityTypeId(), (int) $entity->id(), LanguageWithEntityRevisionItem::SCOPE_PUBLISHED);
      $active_revision->save();
      return;
    }

    // If the translation is synced on the major version that is published,
    // we have to clear the mapping entirely.
    $active_revision->removeLanguageMapping($event->getLangcode());
    if ($active_revision->get('field_language_revision')->isEmpty()) {
      // If there are no more language mappings on the active revision
      // entity, delete the whole thing.
      $active_revision->delete();
      return;
    }

    $active_revision->save();

  }

}
