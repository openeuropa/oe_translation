<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event for determining access to a translation overview page (a tab).
 *
 * Independent of TranslationRequestCreateAccessEvent: a tab can stay
 * visible even when a request can't actually be created yet.
 */
class TranslationRequestOverviewAccessEvent extends TranslationRequestAccessEventBase {

  /**
   * Constructs a new TranslationRequestOverviewAccessEvent.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access result.
   * @param string $bundle
   *   The bundle of the oe_translation_request this operation is for.
   */
  public function __construct(
    protected ContentEntityInterface $entity,
    AccountInterface $account,
    AccessResultInterface $access,
    protected string $bundle,
  ) {
    parent::__construct($account, $access);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(): string {
    return $this->bundle;
  }

}
