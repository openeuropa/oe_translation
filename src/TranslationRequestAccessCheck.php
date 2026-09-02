<?php

declare(strict_types=1);

namespace Drupal\oe_translation;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\Entity\TranslationRequestInterface;
use Drupal\oe_translation\Event\TranslationRequestAcceptAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestOverviewAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestSynchronizeAccessEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Checks a global permission and lets subscribers grant or revoke on top.
 */
class TranslationRequestAccessCheck {

  /**
   * Constructs a new TranslationRequestAccessCheck.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(protected EventDispatcherInterface $eventDispatcher) {}

  /**
   * Checks access to create a request for the given bundle.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity being translated, if known.
   * @param string $translation_request_bundle
   *   The oe_translation_request bundle.
   * @param string|string[] $global_permission
   *   The permission(s) that grant access by default.
   * @param \Drupal\Core\Language\LanguageInterface|null $target
   *   The target language, if known.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkCreateAccess(?AccountInterface $account, ?ContentEntityInterface $entity, string $translation_request_bundle, string|array $global_permission = 'translate any entity', ?LanguageInterface $target = NULL): AccessResultInterface {
    $access = $this->checkPermissionAccess($account, $global_permission);
    return $this->dispatchCreateAccessEvent($entity, $account, $access, $translation_request_bundle, $target);
  }

  /**
   * Checks access to a translation overview page (a tab) for the given bundle.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity being translated, if known.
   * @param string $translation_request_bundle
   *   The oe_translation_request bundle.
   * @param string|string[] $global_permission
   *   The permission(s) that grant access by default.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkOverviewAccess(?AccountInterface $account, ?ContentEntityInterface $entity, string $translation_request_bundle, string|array $global_permission = 'translate any entity'): AccessResultInterface {
    $access = $this->checkPermissionAccess($account, $global_permission);
    return $this->dispatchOverviewAccessEvent($entity, $account, $access, $translation_request_bundle);
  }

  /**
   * Checks access to create a request for an existing translation request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param string|string[] $global_permission
   *   The permission(s) that grant access by default.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkCreateAccessForTranslationRequest(TranslationRequestInterface $translation_request, ?AccountInterface $account, string|array $global_permission = 'translate any entity'): AccessResultInterface {
    $access = $this->checkPermissionAccess($account, $global_permission);
    $access = $this->dispatchCreateAccessEvent($translation_request->getContentEntity(), $account, $access, $translation_request->bundle());
    $access->addCacheableDependency($translation_request);
    return $access;
  }

  /**
   * Checks access to preview a translation request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $oe_translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkPreviewAccess(TranslationRequestInterface $oe_translation_request, AccountInterface $account): AccessResultInterface {
    // The preview page is only ever linked to accept/synchronize form.
    $access = $this->checkAcceptOrSynchronizeAccess($oe_translation_request, $account);
    return $access->addCacheableDependency($oe_translation_request);
  }

  /**
   * Checks access to accept a translation request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkAcceptAccess(TranslationRequestInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $access = $this->checkPermissionAccess($account, 'accept translation request');
    return $this->dispatchAcceptAccessEvent($translation_request, $account, $access);
  }

  /**
   * Checks access to synchronize a translation request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkSynchronizeAccess(TranslationRequestInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $access = $this->checkPermissionAccess($account, 'sync translation request');
    return $this->dispatchSynchronizeAccessEvent($translation_request, $account, $access);
  }

  /**
   * Checks access to either accept or synchronize a translation request.
   *
   * Used by pages that let the user do either operation: access is granted
   * if either one is, letting subscribers of both events grant or revoke.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function checkAcceptOrSynchronizeAccess(TranslationRequestInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $accept_access = $this->checkAcceptAccess($translation_request, $account);
    $sync_access = $this->checkSynchronizeAccess($translation_request, $account);

    $access = $accept_access->isAllowed() || $sync_access->isAllowed()
      ? AccessResult::allowed()
      : AccessResult::forbidden('Neither the accept nor the synchronise access grants access.');

    return $access
      ->inheritCacheability($accept_access)
      ->inheritCacheability($sync_access);
  }

  /**
   * Checks a global permission and builds the corresponding access result.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param string|string[] $global_permission
   *   The permission(s) to check. All of them are required.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkPermissionAccess(?AccountInterface $account, string|array $global_permission): AccessResultInterface {
    if (!$account) {
      return AccessResult::forbidden('The user account is unknown.')->cachePerPermissions();
    }

    foreach ((array) $global_permission as $permission) {
      if (!$account->hasPermission($permission)) {
        return AccessResult::forbidden(sprintf("The user is missing the '%s' permission.", $permission))->cachePerPermissions();
      }
    }

    return AccessResult::allowed()->cachePerPermissions();
  }

  /**
   * Dispatches the event to grant or revoke access to create a request.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity being translated, if known.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access.
   * @param string $bundle
   *   The oe_translation_request bundle.
   * @param \Drupal\Core\Language\LanguageInterface|null $target
   *   The target language, if known.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  protected function dispatchCreateAccessEvent(?ContentEntityInterface $entity, ?AccountInterface $account, AccessResultInterface $access, string $bundle, ?LanguageInterface $target = NULL): AccessResultInterface {
    if (!$entity || !$account) {
      return $access;
    }

    $event = new TranslationRequestCreateAccessEvent($entity, $account, $access, $bundle, $target);
    $this->eventDispatcher->dispatch($event);
    return $event->getAccess();
  }

  /**
   * Dispatches the event to grant or revoke access to an overview page.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity being translated, if known.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account, if known.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access.
   * @param string $bundle
   *   The oe_translation_request bundle.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  protected function dispatchOverviewAccessEvent(?ContentEntityInterface $entity, ?AccountInterface $account, AccessResultInterface $access, string $bundle): AccessResultInterface {
    if (!$entity || !$account) {
      return $access;
    }

    $event = new TranslationRequestOverviewAccessEvent($entity, $account, $access, $bundle);
    $this->eventDispatcher->dispatch($event);
    return $event->getAccess();
  }

  /**
   * Dispatches the event to grant or revoke access to accept a request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  protected function dispatchAcceptAccessEvent(TranslationRequestInterface $translation_request, AccountInterface $account, AccessResultInterface $access): AccessResultInterface {
    if (!$translation_request->getContentEntity()) {
      return $access;
    }

    $event = new TranslationRequestAcceptAccessEvent($translation_request, $account, $access);
    $this->eventDispatcher->dispatch($event);
    return $event->getAccess();
  }

  /**
   * Dispatches the event to grant or revoke access to synchronize a request.
   *
   * @param \Drupal\oe_translation\Entity\TranslationRequestInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param \Drupal\Core\Access\AccessResultInterface $access
   *   The existing access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  protected function dispatchSynchronizeAccessEvent(TranslationRequestInterface $translation_request, AccountInterface $account, AccessResultInterface $access): AccessResultInterface {
    if (!$translation_request->getContentEntity()) {
      return $access;
    }

    $event = new TranslationRequestSynchronizeAccessEvent($translation_request, $account, $access);
    $this->eventDispatcher->dispatch($event);
    return $event->getAccess();
  }

}
