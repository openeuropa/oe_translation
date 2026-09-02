<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event for determining access to create a translation request.
 *
 * This is dispatched before a translation request entity exists, so the
 * content entity and bundle are passed explicitly.
 */
class TranslationRequestCreateAccessEvent extends TranslationRequestAccessEventBase {

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
   * @param \Drupal\Core\Language\LanguageInterface|null $target
   *   The target language, if known at the time of the check.
   */
  public function __construct(
    protected ContentEntityInterface $entity,
    AccountInterface $account,
    AccessResultInterface $access,
    protected string $bundle,
    protected ?LanguageInterface $target = NULL,
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

  /**
   * Returns the target language, if known at the time of the check.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The target language.
   */
  public function getTarget(): ?LanguageInterface {
    return $this->target;
  }

}
