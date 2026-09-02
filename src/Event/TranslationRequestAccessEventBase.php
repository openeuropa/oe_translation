<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Base class for events determining access to a translation request operation.
 */
abstract class TranslationRequestAccessEventBase extends Event {

  /**
   * Constructs a new TranslationRequestAccessEventBase.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access result.
   */
  public function __construct(protected AccountInterface $account, protected AccessResultInterface $access) {}

  /**
   * Returns the entity being translated.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity.
   */
  abstract public function getEntity(): ?ContentEntityInterface;

  /**
   * Returns the bundle of the oe_translation_request this operation is for.
   *
   * @return string
   *   The bundle.
   */
  abstract public function getBundle(): string;

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
