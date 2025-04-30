<?php

declare(strict_types=1);

namespace Drupal\oe_translation_etrans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_etrans\Event\EtransDeliveryEvent;
use Drupal\oe_translation_etrans\Event\EtransFailureEvent;
use Drupal\oe_translation_etrans\TranslationRequestEtransInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Contains the callback paths for etrans notifications.
 */
class EtransController extends ControllerBase {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a EtransController.
   *
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EventDispatcherInterface $eventDispatcher, LoggerChannelFactoryInterface $loggerChannelFactory, EntityTypeManagerInterface $entityTypeManager) {
    $this->eventDispatcher = $eventDispatcher;
    $this->logger = $loggerChannelFactory->get('oe_translation_etrans');
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * The success callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function success(Request $request) {
    // We don't use this yet as a success will inevitably mean also a delivery.
    throw new NotFoundHttpException();
  }

  /**
   * The error callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function error(Request $request) {
    $content = json_decode($request->getContent());
    if (!$content || !isset($content->requestId) || !isset($content->externalReference)) {
      throw new NotFoundHttpException();
    }

    $remote_id = (string) $content->requestId;
    $token = (string) $content->externalReference;

    $translation_request = $this->getTranslationRequest($remote_id, $token);
    if (!$translation_request) {
      // We don't care about any failure notification if we cannot determine
      // a translation request. It's also the only access check we get.
      $this->logger->warning(sprintf('An etrans request was attempted for the request ID %s but for which there was no translation request.', $remote_id));
      throw new NotFoundHttpException();
    }

    $error_message = (string) $content->errorMessage;
    $error_code = (string) $content->errorCode;
    $target_languages = $content->targetLanguages ?? [];

    $event = new EtransFailureEvent($remote_id, $error_code, $error_message, $target_languages);
    $this->eventDispatcher->dispatch($event, EtransFailureEvent::class);

    // We don't need to return any particular thing, even if we have any errors.
    return new Response();
  }

  /**
   * The delivery callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   */
  public function delivery(Request $request) {
    $content = json_decode($request->getContent());
    if (!$content || !isset($content->requestId) || !isset($content->externalReference)) {
      throw new NotFoundHttpException();
    }

    $remote_id = (string) $content->requestId;
    $token = (string) $content->externalReference;

    $translation_request = $this->getTranslationRequest($remote_id, $token);
    if (!$translation_request) {
      $this->logger->warning(sprintf('An etrans delivery was attempted for the request ID %s but for which there was no translation request.', $remote_id));
      throw new NotFoundHttpException();
    }

    $target_language = $content->targetLanguage;
    $this->logger->info('The etrans for request ID: <strong>@request_id</strong> in @language has been received: @etrans', [
      '@request_id' => $remote_id,
      '@language' => $target_language,
      '@etrans' => $content->result,
    ]);
    $translation = base64_decode($content->result);
    $event = new EtransDeliveryEvent($remote_id, $target_language, $translation);
    $this->eventDispatcher->dispatch($event, EtransDeliveryEvent::class);

    // We don't need to return any particular thing, even if we have any errors.
    return new Response();
  }

  /**
   * Route for marking a failed request as finished.
   *
   * @param \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $translation_request
   *   The translation request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function finishFailedRequest(TranslationRequestEtransInterface $translation_request, Request $request): RedirectResponse {
    $translation_request->setRequestStatus(TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED_FINISHED);
    $translation_request->save();

    $destination = $request->query->get('destination');
    if (!$destination) {
      throw new NotFoundHttpException();
    }

    return new RedirectResponse($destination);
  }

  /**
   * Checks access to the finishFailedRequest route.
   *
   * @param \Drupal\oe_translation_etrans\TranslationRequestEtransInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function finishFailedRequestAccess(TranslationRequestEtransInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    $cache->addCacheableDependency($translation_request);

    if (!$account->hasPermission('translate any entity')) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    // Only failed requests can be marked.
    if ($translation_request->getRequestStatus() !== TranslationRequestRemoteInterface::STATUS_REQUEST_FAILED) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    return AccessResult::allowed();
  }

  /**
   * Returns the translation request based on the request ID.
   *
   * @param string $request_id
   *   The request ID.
   * @param string $token
   *   The access token.
   *
   * @return null|\Drupal\oe_translation_etrans\TranslationRequestEtransInterface
   *   The translation request.
   */
  protected function getTranslationRequest(string $request_id, string $token): ?TranslationRequestEtransInterface {
    $ids = $this->entityTypeManager->getStorage('oe_translation_request')
      ->getQuery()
      ->condition('remote_id', $request_id)
      ->condition('etrans_access_token', $token)
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return NULL;
    }

    $id = reset($ids);
    return $this->entityTypeManager->getStorage('oe_translation_request')->load($id);
  }

}
