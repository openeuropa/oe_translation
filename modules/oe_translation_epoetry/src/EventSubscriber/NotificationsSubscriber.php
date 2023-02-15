<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\EPoetry\Notification\Event\Product\BaseEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\DeliveryEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeAcceptedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeCancelledEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeClosedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeOngoingEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeReadyToBeSentEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRequestedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSentEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeAcceptedEvent as RequestStatusChangeAcceptedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeCancelledEvent as RequestStatusChangeCancelledEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeSuspendedEvent as RequestStatusChangeSuspendedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeExecutedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeRejectedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSuspendedEvent;
use OpenEuropa\EPoetry\Notification\Type\RequestReference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the ePoetry notification events.
 */
class NotificationsSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content formatter.
   *
   * @var \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface
   */
  protected $contentFormatter;

  /**
   * Constructs a new NotificationsSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $contentFormatter
   *   The content formatter.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ContentFormatterInterface $contentFormatter) {
    $this->entityTypeManager = $entityTypeManager;
    $this->contentFormatter = $contentFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      StatusChangeRequestedEvent::NAME => 'onProductRequested',
      StatusChangeCancelledEvent::NAME => 'onProductCancelled',
      StatusChangeAcceptedEvent::NAME => 'onProductAccepted',
      StatusChangeOngoingEvent::NAME => 'onProductOngoing',
      StatusChangeReadyToBeSentEvent::NAME => 'onProductReadyToBeSent',
      StatusChangeSentEvent::NAME => 'onProductSent',
      StatusChangeClosedEvent::NAME => 'onProductClosed',
      StatusChangeSuspendedEvent::NAME => 'onProductSuspended',
      RequestStatusChangeAcceptedEvent::NAME => 'onRequestAccepted',
      StatusChangeRejectedEvent::NAME => 'onRequestRejected',
      RequestStatusChangeCancelledEvent::NAME => 'onRequestCancelled',
      StatusChangeExecutedEvent::NAME => 'onRequestExecuted',
      RequestStatusChangeSuspendedEvent::NAME => 'onRequestSuspended',
      DeliveryEvent::NAME => 'onProductDelivery',
    ];
  }

  /**
   * Handles the product status change: Requested.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRequestedEvent $event
   *   The event.
   */
  public function onProductRequested(StatusChangeRequestedEvent $event): void {
    // For the Requested status, we don't update anything, we leave it as
    // Active.
    $event->setSuccessResponse('No status update done, but on purpose.');
  }

  /**
   * Handles the product status change: Cancelled.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeCancelledEvent $event
   *   The event.
   */
  public function onProductCancelled(StatusChangeCancelledEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED);
  }

  /**
   * Handles the product status change: Accepted.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeAcceptedEvent $event
   *   The event.
   */
  public function onProductAccepted(StatusChangeAcceptedEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_EPOETRY_ACCEPTED);
  }

  /**
   * Handles the product status change: Ongoing.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeOngoingEvent $event
   *   The event.
   */
  public function onProductOngoing(StatusChangeOngoingEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_ONGOING);
  }

  /**
   * Handles the product status change: ReadyToBeSent.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeReadyToBeSentEvent $event
   *   The event.
   */
  public function onProductReadyToBeSent(StatusChangeReadyToBeSentEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_READY);
  }

  /**
   * Handles the product status change: Sent.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSentEvent $event
   *   The event.
   */
  public function onProductSent(StatusChangeSentEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SENT);
  }

  /**
   * Handles the product status change: Closed.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeClosedEvent $event
   *   The event.
   */
  public function onProductClosed(StatusChangeClosedEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CLOSED);
  }

  /**
   * Handles the product status change: Suspended.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSuspendedEvent $event
   *   The event.
   */
  public function onProductSuspended(StatusChangeSuspendedEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SUSPENDED);
  }

  /**
   * Helper method that updates the product status.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\BaseEvent $event
   *   The event.
   * @param string $status
   *   The status.
   */
  protected function onProductStatusChange(BaseEvent $event, string $status): void {
    $product = $event->getProduct();
    $translation_request = $this->getTranslationRequest($product->getProductReference()->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    $language = $product->getProductReference()->getLanguage();
    // @todo handle language mapping.
    $language = strtolower($language);

    $translation_request->updateTargetLanguageStatus($language, $status);
    $translation_request->save();
    // @todo log.
    $event->setSuccessResponse('The language status has been updated successfully.');
  }

  /**
   * Handles the product (translation) delivery and saves it onto the request.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\DeliveryEvent $event
   *   The event.
   */
  public function onProductDelivery(DeliveryEvent $event): void {
    $product = $event->getProduct();
    $translation_request = $this->getTranslationRequest($product->getProductReference()->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    $language = $product->getProductReference()->getLanguage();
    // @todo handle language mapping.
    $language = strtolower($language);

    // Process the translated file.
    $file = $product->getFile();
    $data = $this->contentFormatter->import($file, $translation_request);
    $translation_request->setTranslatedData($language, reset($data));
    $translation_request->updateTargetLanguageStatus($language, TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW);
    $translation_request->save();
    $event->setSuccessResponse('The translation has been saved.');
    // @todo log.
  }

  /**
   * Handles the request status change: Accepted.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeAcceptedEvent $event
   *   The event.
   */
  public function onRequestAccepted(RequestStatusChangeAcceptedEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');
      // @todo log.
      return;
    }

    // Set the ePoetry request status but keep the request active.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_ACTIVE);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    // @todo log.
  }

  /**
   * Handles the request status change: Rejected.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeRejectedEvent $event
   *   The event.
   */
  public function onRequestRejected(StatusChangeRejectedEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    // Set the ePoetry request status and finish the request if it was rejected.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
    $translation_request->save();
    // @todo log and log also the reason why it was rejected.
    $event->setSuccessResponse('The translation request status has been updated successfully.');
  }

  /**
   * Handles the request status change: Executed.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeExecutedEvent $event
   *   The event.
   */
  public function onRequestExecuted(StatusChangeExecutedEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    // Set the ePoetry request status and finish the request if it was executed.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
    $translation_request->save();
    // @todo log and log also the reason why it was rejected.
    $event->setSuccessResponse('The translation request status has been updated successfully.');
  }

  /**
   * Handles the request status change: Cancelled.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeCancelledEvent $event
   *   The event.
   */
  public function onRequestCancelled(RequestStatusChangeCancelledEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    // Set the ePoetry request status and finish the request if it was
    // cancelled.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
    $translation_request->save();
    // @todo log and log also the reason why it was rejected.
    $event->setSuccessResponse('The translation request status has been updated successfully.');
  }

  /**
   * Handles the request status change: Suspended.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeSuspendedEvent $event
   *   The event.
   */
  public function onRequestSuspended(RequestStatusChangeSuspendedEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');

      // @todo log.
      return;
    }

    // Set the ePoetry request status.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED);
    $translation_request->save();
    // @todo log and log also the reason why it was rejected.
    $event->setSuccessResponse('The translation request status has been updated successfully.');
  }

  /**
   * Returns the translation request based on the linguistic request.
   *
   * @return \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface|null
   *   The translation request.
   */
  protected function getTranslationRequest(RequestReference $reference): ?TranslationRequestEpoetryInterface {
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')->getQuery()
      ->condition('bundle', 'epoetry')
      ->condition('request_id.code', $reference->getRequesterCode())
      ->condition('request_id.year', $reference->getYear())
      ->condition('request_id.number', $reference->getNumber())
      ->condition('request_id.part', $reference->getPart())
      ->condition('request_id.version', $reference->getVersion())
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      // If this happens, it means the request was deleted in the meantime,
      // which should not happen.
      return NULL;
    }

    $id = reset($ids);
    return $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
  }

}
