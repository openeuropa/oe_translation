<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_active_revision\Event\ActiveRevisionMappingAccessEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Checks access to the active revision mapping routes.
 */
class ActiveRevisionMappingAccessCheck {

  /**
   * Constructs a new ActiveRevisionMappingAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected EventDispatcherInterface $eventDispatcher) {}

  /**
   * Checks access to manage the active revision mapping of an entity.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_id
   *   The entity ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(string $entity_type, string $entity_id, AccountInterface $account): AccessResultInterface {
    $access = $account->hasPermission('translate any entity')
      ? AccessResult::allowed()
      : AccessResult::forbidden("The user is missing the 'translate any entity' permission.");
    $access->cachePerPermissions();

    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      return $access;
    }

    $event = new ActiveRevisionMappingAccessEvent($entity, $account, $access);
    $this->eventDispatcher->dispatch($event);
    return $event->getAccess()->addCacheableDependency($entity);
  }

}
