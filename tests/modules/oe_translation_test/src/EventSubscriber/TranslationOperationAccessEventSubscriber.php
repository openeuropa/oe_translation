<?php

declare(strict_types=1);

namespace Drupal\oe_translation_test\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\State\StateInterface;
use Drupal\oe_translation\Event\TranslationRequestAcceptAccessEvent;
use Drupal\oe_translation\Event\TranslationRequestAccessEventBase;
use Drupal\oe_translation\Event\TranslationRequestCreateAccessEvent;
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
      TranslationRequestCreateAccessEvent::EVENT => 'checkCreateAccess',
      TranslationRequestAcceptAccessEvent::EVENT => 'checkAcceptAccess',
      TranslationRequestSynchronizeAccessEvent::EVENT => 'checkSyncAccess',
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
