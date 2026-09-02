<?php

declare(strict_types=1);

namespace Drupal\oe_translation_active_revision\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event for determining access to manage an entity's active revision mapping.
 */
class ActiveRevisionMappingAccessEvent extends Event {

  /**
   * Constructs a new ActiveRevisionMappingAccessEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity the mapping applies to.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access result.
   */
  public function __construct(protected ContentEntityInterface $entity, protected AccountInterface $account, protected AccessResultInterface $access) {}

  /**
   * Returns the entity the mapping applies to.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Returns the account.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The account.
   */
  public function getAccount(): AccountInterface {
    return $this->account;
  }

  /**
   * Returns the access result.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function getAccess(): AccessResultInterface {
    return $this->access instanceof AccessResultInterface ? $this->access : AccessResult::neutral();
  }

  /**
   * Sets the access result.
   *
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The access.
   */
  public function setAccess(AccessResultInterface $access): void {
    $this->access = $access;
  }

}
