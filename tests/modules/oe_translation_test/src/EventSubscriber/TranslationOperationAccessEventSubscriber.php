<?php

declare(strict_types=1);

namespace Drupal\oe_translation_test\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\Event\TranslationAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestAcceptAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestAccessEventBase;
use Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestOverviewAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestSynchronizeAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the translation request access events.
 */
class TranslationOperationAccessEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a TranslationOperationAccessEventSubscriber.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      TranslationRequestCreateAccessEvent::class => 'checkCreateAccess',
      TranslationRequestOverviewAccessEvent::class => 'checkOverviewAccess',
      TranslationRequestAcceptAccessEvent::class => 'checkAcceptAccess',
      TranslationRequestSynchronizeAccessEvent::class => 'checkSyncAccess',
      // @phpstan-ignore classConstant.deprecatedClass
      TranslationAccessEvent::EVENT => 'checkDeprecatedAccess',
    ];
  }

  /**
   * Overrides the access for the create operation.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent $event
   *   The event.
   */
  public function checkCreateAccess(TranslationRequestCreateAccessEvent $event): void {
    $this->checkOperationAccess($event, 'create');
  }

  /**
   * Overrides the access for the overview operation.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestOverviewAccessEvent $event
   *   The event.
   */
  public function checkOverviewAccess(TranslationRequestOverviewAccessEvent $event): void {
    $this->checkOperationAccess($event, 'overview');
  }

  /**
   * Overrides the access for the accept operation.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestAcceptAccessEvent $event
   *   The event.
   */
  public function checkAcceptAccess(TranslationRequestAcceptAccessEvent $event): void {
    $this->checkOperationAccess($event, 'accept');
  }

  /**
   * Overrides the access for the sync operation.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestSynchronizeAccessEvent $event
   *   The event.
   */
  public function checkSyncAccess(TranslationRequestSynchronizeAccessEvent $event): void {
    $this->checkOperationAccess($event, 'sync');
  }

  /**
   * Overrides the access for the deprecated TranslationAccessEvent.
   *
   * Kept so the deprecated event, still functional for backwards
   * compatibility, remains covered by a test.
   *
   * @param \Drupal\oe_translation\Event\TranslationAccessEvent $event
   *   The event.
   */
  // @phpstan-ignore parameter.deprecatedClass
  public function checkDeprecatedAccess(TranslationAccessEvent $event): void {
    $this->state->resetCache();
    $overrides = $this->state->get('oe_translation_test.operation_access_overrides', []);
    if (!isset($overrides['deprecated'])) {
      return;
    }

    $access = $overrides['deprecated'] === 'allowed' ? AccessResult::allowed() : AccessResult::forbidden('Forbidden by the test subscriber.');
    $event->setAccess($access);
  }

  /**
   * Overrides the access for the operations configured in state.
   *
   * @param \Drupal\oe_translation\Event\TranslationRequestAccessEventBase $event
   *   The event.
   * @param string $operation
   *   The operation the event corresponds to.
   */
  protected function checkOperationAccess(TranslationRequestAccessEventBase $event, string $operation): void {
    $this->state->resetCache();
    $overrides = $this->state->get('oe_translation_test.operation_access_overrides', []);
    if (!isset($overrides[$operation])) {
      return;
    }

    $access = $overrides[$operation] === 'allowed' ? AccessResult::allowed() : AccessResult::forbidden('Forbidden by the test subscriber.');
    $event->setAccess($access);
  }

}
