<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event for determining access to create a translation request.
 *
 * This is dispatched before a translation request entity exists, so the
 * content entity and bundle are passed explicitly.
 */
class TranslationRequestCreateAccessEvent extends TranslationRequestAccessEventBase {

  const EVENT = 'translation_request_create_access_event';

  /**
   * Constructs a new TranslationRequestCreateAccessEvent.
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
