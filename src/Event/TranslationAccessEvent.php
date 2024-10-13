<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Event for determining the access to translate content.
 *
 * Rules can differ on whether a given entity should be translatable so we allow
 * modules to specify it.
 */
class TranslationAccessEvent extends Event {

  const EVENT = 'translation_access_event';

  /**
   * The entity being translated.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The source language.
   *
   * @var \Drupal\Core\Language\Language
   */
  protected $source;

  /**
   * The target language.
   *
   * @var \Drupal\Core\Language\Language
   */
  protected $target;

  /**
   * The account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The access result.
   *
   * @var \Drupal\Core\Access\AccessResultInterface
   */
  protected $access;

  /**
   * TranslationAccessEvent constructor.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access result.
   * @param \Drupal\Core\Language\LanguageInterface|null $source
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface|null $target
   *   The target language.
   */
  public function __construct(ContentEntityInterface $entity, AccountInterface $account, AccessResultInterface $access, ?LanguageInterface $source = NULL, ?LanguageInterface $target = NULL) {
    $this->entity = $entity;
    $this->account = $account;
    $this->source = $source;
    $this->target = $target;
    $this->access = $access;
  }

  /**
   * Returns the entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function getEntity(): ContentEntityInterface {
    return $this->entity;
  }

  /**
   * Sets the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   */
  public function setEntity(ContentEntityInterface $entity): void {
    $this->entity = $entity;
  }

  /**
   * Returns the source language.
   *
   * @return \Drupal\Core\Language\Language
   *   The source language.
   */
  public function getSource(): Language {
    return $this->source;
  }

  /**
   * Sets the source language.
   *
   * @param \Drupal\Core\Language\Language $source
   *   The source language.
   */
  public function setSource(Language $source): void {
    $this->source = $source;
  }

  /**
   * Returns the target language.
   *
   * @return \Drupal\Core\Language\Language
   *   The target language.
   */
  public function getTarget(): Language {
    return $this->target;
  }

  /**
   * Sets the target language..
   *
   * @param \Drupal\Core\Language\Language $target
   *   The target language..
   */
  public function setTarget(Language $target): void {
    $this->target = $target;
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
   * Sets the account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   */
  public function setAccount(AccountInterface $account): void {
    $this->account = $account;
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
