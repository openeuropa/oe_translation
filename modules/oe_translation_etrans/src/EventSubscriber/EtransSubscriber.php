<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactoryInterface;
use Drupal\oe_translation\Entity\TranslationRequestLogInterface;
use Drupal\oe_translation\LanguageMapper;
use Drupal\oe_translation_content_formatter\ContentFormatter\ContentFormatterInterface;
use Drupal\oe_translation_etrans\Event\EtransDeliveryEvent;
use Drupal\oe_translation_etrans\Event\EtransFailureEvent;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to the etrans events.
 */
class EtransSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The content formatter.
   *
   * @var \Drupal\oe_translation_content_formatter\ContentFormatter\ContentFormatterInterface
   */
  protected $contentFormatter;

  /**
   * The queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs an EtransSubscriber.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\oe_translation_content_formatter\ContentFormatter\ContentFormatterInterface $contentFormatter
   *   The content formatter.
   * @param \Drupal\Core\Queue\QueueFactoryInterface $queue_factory
   *   The queue factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerChannelFactory, ContentFormatterInterface $contentFormatter, QueueFactoryInterface $queue_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $loggerChannelFactory->get('oe_translation_etrans');
    $this->contentFormatter = $contentFormatter;
    $this->queue = $queue_factory->get('oe_translation_etrans_delivery');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      EtransDeliveryEvent::class => 'onDelivery',
      EtransFailureEvent::class => 'onFailure',
    ];
  }

  /**
   * Handles the incoming etrans.
   *
   * We don't save it on the translation request but instead we add it to a
   * queue to be processed by cron. The reason is that etrans can send many
   * such requests, one close after the other and we may run into consistency
   * issues.
   *
   * @param \Drupal\oe_translation_etrans\Event\EtransDeliveryEvent $event
   *   The event.
   */
  public function onDelivery(EtransDeliveryEvent $event): void {
    $request_id = $event->getRequestId();
    $translation = $event->getTranslation();
    $language = $event->getLanguage();

    $translation_request = $this->getTranslationRequest($request_id);
    $language = LanguageMapper::getDrupalLanguageCode($language, $translation_request);

    try {
      $data = $this->contentFormatter->import($translation, $translation_request);
    }
    catch (\Exception $exception) {
      $this->logger->error('The etrans notification did not provide a valid translation. Exception: @exception Request ID: <strong>@request_id</strong>. Translation: @translation', [
        '@request_id' => $request_id,
        '@exception' => $exception->getMessage(),
        '@translation' => $translation,
      ]);
      return;
    }

    if (!$data) {
      $this->logger->error('The etrans notification did not provide a valid translation. Request ID: <strong>@request_id</strong>.', ['@request_id' => $request_id]);
      return;
    }

    $this->queue->createItem([
      'translation_request_id' => $translation_request->id(),
      'language' => $language,
      'data' => reset($data),
    ]);
  }

  /**
   * Handles the failure event.
   *
   * @param \Drupal\oe_translation_etrans\Event\EtransFailureEvent $event
   *   The event.
   */
  public function onFailure(EtransFailureEvent $event): void {
    $request_id = $event->getRequestId();
    $translation_request = $this->getTranslationRequest($request_id);
    $error_code = $event->getErrorCode();
    $error_message = $event->getErrorMessage();
    $target_languages = $event->getTargetLanguages();
    $target_languages_string = count($target_languages) > 1 ? implode(', ', $target_languages) : reset($target_languages);

    $message = 'Etrans sent a failure notification for the Request ID: <strong>@request_id</strong> with the following error code @code and error message: @error_message.';
    $variables = [
      '@request_id' => $request_id,
      '@code' => $error_code,
      '@error_message' => $error_message,
    ];
    if ($target_languages) {
      $message .= ' The notification was for the following languages: @languages';
      $variables['@languages'] = $target_languages_string;
    }

    $this->logger->error($message, $variables);
    $translation_request->log($message, $variables, TranslationRequestLogInterface::ERROR);
    // In case they send a failure for 1 language vs multiple language, mark
    // the request failed or not.
    if (count($target_languages) === 1) {
      $language = reset($target_languages);
      $language = LanguageMapper::getDrupalLanguageCode($language, $translation_request);
      $translation_request->updateTargetLanguageStatus($language, TranslationRequestEtransInterface::STATUS_LANGUAGE_FAILED);
    }
    else {
      $translation_request->setRequestStatus(TranslationRequestEtransInterface::STATUS_REQUEST_FAILED);
    }

    $translation_request->save();
  }

  /**
   * Returns the translation request based on the request ID.
   *
   * @param string $request_id
   *   The request ID.
   *
   * @return \Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   *   The translation request.
   */
  protected function getTranslationRequest(string $request_id): TranslationRequestEtransInterface {
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')
      ->getQuery()
      ->condition('remote_id', $request_id)
      ->accessCheck(FALSE)
      ->execute();

    $id = reset($ids);
    return $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
  }

}
