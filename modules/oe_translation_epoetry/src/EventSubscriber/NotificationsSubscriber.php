<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_epoetry\EpoetryLanguageMapper;
use Drupal\oe_translation_epoetry\Event\EpoetryNotificationRequestUpdateEvent;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\RemoteTranslationSynchroniser;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\EPoetry\Notification\Event\Product\DeliveryEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\ProductEventInterface;
use OpenEuropa\EPoetry\Notification\Event\Product\ProductEventWithDeadlineInterface;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeAcceptedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeCancelledEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeClosedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeOngoingEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeReadyToBeSentEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRejectedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRequestedEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSentEvent;
use OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeSuspendedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\BaseEvent as RequestBaseEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeAcceptedEvent as RequestStatusChangeAcceptedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeCancelledEvent as RequestStatusChangeCancelledEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeExecutedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeRejectedEvent as RequestStatusChangeRejectedEvent;
use OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeSuspendedEvent as RequestStatusChangeSuspendedEvent;
use OpenEuropa\EPoetry\Notification\Type\RequestReference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Subscribes to the ePoetry notification events.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
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
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The translation synchroniser.
   *
   * @var \Drupal\oe_translation_remote\RemoteTranslationSynchroniser
   */
  protected $translationSynchroniser;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lockBackend;

  /**
   * Constructs a new NotificationsSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\oe_translation_epoetry\ContentFormatter\ContentFormatterInterface $contentFormatter
   *   The content formatter.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\oe_translation_remote\RemoteTranslationSynchroniser $translationSynchroniser
   *   The translation synchroniser.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Lock\LockBackendInterface $lockBackend
   *   The lock backend.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ContentFormatterInterface $contentFormatter, LanguageManagerInterface $languageManager, LoggerChannelFactoryInterface $loggerChannelFactory, RemoteTranslationSynchroniser $translationSynchroniser, EventDispatcherInterface $eventDispatcher, LockBackendInterface $lockBackend) {
    $this->entityTypeManager = $entityTypeManager;
    $this->contentFormatter = $contentFormatter;
    $this->languageManager = $languageManager;
    $this->logger = $loggerChannelFactory->get('oe_translation_epoetry');
    $this->translationSynchroniser = $translationSynchroniser;
    $this->eventDispatcher = $eventDispatcher;
    $this->lockBackend = $lockBackend;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      StatusChangeRequestedEvent::NAME => 'onProductRequested',
      StatusChangeCancelledEvent::NAME => 'onProductCancelled',
      StatusChangeRejectedEvent::NAME => 'onProductRejected',
      StatusChangeAcceptedEvent::NAME => 'onProductAccepted',
      StatusChangeOngoingEvent::NAME => 'onProductOngoing',
      StatusChangeReadyToBeSentEvent::NAME => 'onProductReadyToBeSent',
      StatusChangeSentEvent::NAME => 'onProductSent',
      StatusChangeClosedEvent::NAME => 'onProductClosed',
      StatusChangeSuspendedEvent::NAME => 'onProductSuspended',
      RequestStatusChangeAcceptedEvent::NAME => 'onRequestAccepted',
      RequestStatusChangeRejectedEvent::NAME => 'onRequestRejected',
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
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REQUESTED);
  }

  /**
   * Handles the product status change: Cancelled.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeCancelledEvent $event
   *   The event.
   */
  public function onProductCancelled(StatusChangeCancelledEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_CANCELLED, TranslationRequestLogInterface::WARNING);
  }

  /**
   * Handles the product status change: Rejected.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\StatusChangeRejectedEvent $event
   *   The event.
   */
  public function onProductRejected(StatusChangeRejectedEvent $event): void {
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_REJECTED, TranslationRequestLogInterface::WARNING);
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
    $this->onProductStatusChange($event, TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SUSPENDED, TranslationRequestLogInterface::WARNING);
  }

  /**
   * Helper method that updates the product status.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Product\ProductEventInterface $event
   *   The event.
   * @param string $status
   *   The status.
   * @param string $log_type
   *   The type of log to create.
   */
  protected function onProductStatusChange(ProductEventInterface $event, string $status, string $log_type = TranslationRequestLogInterface::INFO): void {
    $product = $event->getProduct();
    $translation_request = $this->getTranslationRequest($product->getProductReference()->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');
      $reference = $this->formatRequestReference($product->getProductReference()->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $language = $product->getProductReference()->getLanguage();
    $langcode = EpoetryLanguageMapper::getDrupalLanguageCode($language, $translation_request);
    $language = $this->languageManager->getLanguage($langcode);

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $translation_request->log('The <strong>@language</strong> product could not be updated to <strong>@status</strong> due to Lock issues. Please check the logs.', [
          '@language' => $language->getName(),
          '@status' => $status,
        ], TranslationRequestLogInterface::ERROR);
        return;
      }
    }

    // If we already have a translation for this language, do not change the
    // status anymore. Any status updates that may come after have no real
    // relevance for our system because from this moment we start our own flow
    // of reviewing and syncing the translation values.
    $data = $translation_request->getTranslatedData();
    if (!isset($data[$language->getId()])) {
      $translation_request->updateTargetLanguageStatus($langcode, $status);
    }

    $translation_request->log('The <strong>@language</strong> product status has been updated to <strong>@status</strong>.', [
      '@language' => $language->getName(),
      '@status' => $status,
    ], $log_type);

    if ($event instanceof ProductEventWithDeadlineInterface && $event->getAcceptedDeadline() instanceof \DateTimeInterface) {
      $translation_request->updateTargetLanguageAcceptedDeadline($langcode, $event->getAcceptedDeadline());
    }

    // Dispatch an event to allow others to alter the translation request
    // before saving it.
    $notification_event = new EpoetryNotificationRequestUpdateEvent($translation_request, $event);
    $this->eventDispatcher->dispatch($notification_event, EpoetryNotificationRequestUpdateEvent::NAME);

    $translation_request->save();
    $event->setSuccessResponse('The language status has been updated successfully.');
    $this->lockBackend->release($lock_id);
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
      $reference = $this->formatRequestReference($product->getProductReference()->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $language = $product->getProductReference()->getLanguage();
    $langcode = EpoetryLanguageMapper::getDrupalLanguageCode($language, $translation_request);
    $language = $this->languageManager->getLanguage($langcode);

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $translation_request->log('The <strong>@language</strong> translation could not be delivered to Lock issues. Please check the logs.', [
          '@language' => $language->getName(),
        ], TranslationRequestLogInterface::ERROR);
        return;
      }
    }

    // Process the translated file.
    $file = $product->getFile();
    $data = $this->contentFormatter->import($file, $translation_request);
    $translation_request->setTranslatedData($langcode, reset($data));
    $translation_request->log('The <strong>@language</strong> translation has been delivered.', ['@language' => $language->getName()]);
    // Check if we need to auto-accept and/or auto-sync the request.
    $auto_accept = $translation_request->isAutoAccept();
    $auto_sync = $translation_request->isAutoSync();
    // Check the provider configuration because we may have global settings for
    // the auto-accept feature.
    $provider_configuration = $translation_request->getTranslatorProvider()->getProviderConfiguration();
    if ((bool) $provider_configuration['auto_accept'] === TRUE) {
      $auto_accept = TRUE;
    }

    // We can only auto-accept and auto-sync if the language has not already
    // been synced. This is to prevent issues if ePoetry sends a translation
    // again after it has done already and the request is potentially finished.
    if ($translation_request->getTargetLanguage($langcode)->getStatus() === TranslationRequestEpoetryInterface::STATUS_LANGUAGE_SYNCHRONISED) {
      $auto_accept = FALSE;
      $auto_sync = FALSE;
    }

    $status = $auto_accept ? TranslationRequestRemoteInterface::STATUS_LANGUAGE_ACCEPTED : TranslationRequestRemoteInterface::STATUS_LANGUAGE_REVIEW;
    $translation_request->updateTargetLanguageStatus($langcode, $status);
    if ($auto_accept) {
      $translation_request->log('The <strong>@language</strong> translation has been automatically accepted.', ['@language' => $language->getName()]);
    }

    if ($auto_sync) {
      $this->translationSynchroniser->synchronise($translation_request, $langcode, TRUE);
      // The log for this happens in the sync service.
    }
    $translation_request->save();
    $event->setSuccessResponse('The translation has been saved.');
    $this->lockBackend->release($lock_id);
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
      $reference = $this->formatRequestReference($linguistic_request->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $this->logRequestStatusChangeEvent($event, $translation_request, TRUE);
        return;
      }
    }

    // Set the ePoetry request status but keep the request active.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_ACCEPTED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_REQUESTED);
    $this->logRequestStatusChangeEvent($event, $translation_request);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    $this->lockBackend->release($lock_id);
  }

  /**
   * Handles the request status change: Rejected.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\StatusChangeRejectedEvent $event
   *   The event.
   */
  public function onRequestRejected(RequestStatusChangeRejectedEvent $event): void {
    $linguistic_request = $event->getLinguisticRequest();
    $translation_request = $this->getTranslationRequest($linguistic_request->getRequestReference());
    if (!$translation_request) {
      $event->setErrorResponse('Missing translation request');
      $reference = $this->formatRequestReference($linguistic_request->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $this->logRequestStatusChangeEvent($event, $translation_request, TRUE);
        return;
      }
    }

    // Set the ePoetry request status and finish the request if it was rejected.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
    $this->logRequestStatusChangeEvent($event, $translation_request);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    $this->lockBackend->release($lock_id);
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
      $reference = $this->formatRequestReference($linguistic_request->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $this->logRequestStatusChangeEvent($event, $translation_request, TRUE);
        return;
      }
    }

    // Set the ePoetry request status.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_EXECUTED);
    $this->logRequestStatusChangeEvent($event, $translation_request);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    $this->lockBackend->release($lock_id);
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
      $reference = $this->formatRequestReference($linguistic_request->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $this->logRequestStatusChangeEvent($event, $translation_request, TRUE);
        return;
      }
    }

    // Set the ePoetry request status and finish the request if it was
    // cancelled.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED);
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FINISHED);
    $this->logRequestStatusChangeEvent($event, $translation_request);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    $this->lockBackend->release($lock_id);
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
      $reference = $this->formatRequestReference($linguistic_request->getRequestReference());
      $this->logger->error('The ePoetry notification could not find a translation request for the reference: <strong>@reference</strong>.', ['@reference' => $reference]);
      return;
    }

    $lock_id = 'oe_translation_epoetry_lock_' . $translation_request->id();
    if (!$this->lockBackend->acquire($lock_id)) {
      $this->logger->log('notice', sprintf('Lock already acquired: The translation request %s is already being updated.', $translation_request->id()));
      if ($this->lockBackend->wait($lock_id, 60)) {
        $message = sprintf('Acquiring lock failed: The translation request %s cannot be updated.', $translation_request->id());
        $this->logger->error($message);
        $this->logRequestStatusChangeEvent($event, $translation_request, TRUE);
        return;
      }
    }

    // Set the ePoetry request status.
    $translation_request->setEpoetryRequestStatus(TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED);
    $this->logRequestStatusChangeEvent($event, $translation_request);
    $translation_request->save();
    $event->setSuccessResponse('The translation request status has been updated successfully.');
    $this->lockBackend->release($lock_id);
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
      // It should not be rejected because we can have multiple requests with
      // the same IDs if one was rejected.
      ->condition('epoetry_status', TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED, '!=')
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

  /**
   * Formats the request reference into a readable string.
   *
   * @param \OpenEuropa\EPoetry\Notification\Type\RequestReference $reference
   *   The reference.
   *
   * @return string
   *   The string reference.
   */
  protected function formatRequestReference(RequestReference $reference): string {
    $values = [
      $reference->getRequesterCode(),
      $reference->getYear(),
      $reference->getNumber(),
      $reference->getVersion(),
      $reference->getPart(),
      $reference->getProductType(),
    ];

    return implode('/', $values);
  }

  /**
   * Logs the message for the request status change event.
   *
   * @param \OpenEuropa\EPoetry\Notification\Event\Request\BaseEvent $event
   *   The event.
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $request
   *   The request.
   * @param bool $failed
   *   Whether the update failed or not due to lock.
   */
  protected function logRequestStatusChangeEvent(RequestBaseEvent $event, TranslationRequestEpoetryInterface $request, bool $failed = FALSE): void {
    $message = 'The request has been <strong>@status</strong> by ePoetry.';
    if ($failed) {
      $message = 'The request could not be <strong>@status</strong> by ePoetry due to Lock issues.';
    }
    $variables = ['@status' => $event->getLinguisticRequest()->getStatus()];
    if ($event->getPlanningAgent()) {
      $message .= ' Planning agent: <strong>@agent</strong>.';
      $variables['@agent'] = $event->getPlanningAgent();
    }
    if ($event->getPlanningSector()) {
      $message .= ' Planning sector: <strong>@sector</strong>.';
      $variables['@sector'] = $event->getPlanningSector();
    }
    if ($event->getMessage()) {
      $message .= ' Message: <strong>@message</strong>.';
      $variables['@message'] = $event->getMessage();
    }

    $type = TranslationRequestLogInterface::INFO;
    // Change the type of it it's not a normal/expected happy path.
    if (in_array($event->getLinguisticRequest()->getStatus(), [
      TranslationRequestEpoetryInterface::STATUS_REQUEST_REJECTED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_SUSPENDED,
      TranslationRequestEpoetryInterface::STATUS_REQUEST_CANCELLED,
    ])) {
      $type = TranslationRequestLogInterface::WARNING;
    }
    if ($failed) {
      $type = TranslationRequestLogInterface::ERROR;
    }
    $request->log($message, $variables, $type);
  }

}
