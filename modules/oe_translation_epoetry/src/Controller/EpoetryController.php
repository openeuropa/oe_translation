<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface;
use Drupal\oe_translation_epoetry\NotificationEndpointResolver;
use Drupal\oe_translation_epoetry\NotificationTicketValidation;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use Http\Discovery\Psr17Factory;
use OpenEuropa\EPoetry\NotificationServerFactory;
use OpenEuropa\EPoetry\Serializer\Serializer;
use OpenEuropa\EPoetry\TicketValidation\TicketValidationInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller for ePoetry routes.
 */
class EpoetryController extends ControllerBase {

  /**
   * The new version request handler.
   *
   * @var \Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface
   */
  protected $newVersionRequestHandler;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerChannelFactory;

  /**
   * The ticket validation service.
   *
   * @var \OpenEuropa\EPoetry\TicketValidation\TicketValidationInterface
   */
  protected $ticketValidation;

  /**
   * Constructs a EpoetryController.
   *
   * @param \Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface $newVersionRequestHandler
   *   The new version request handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \OpenEuropa\EPoetry\TicketValidation\TicketValidationInterface $ticketValidation
   *   The ticket validation service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EpoetryOngoingNewVersionRequestHandlerInterface $newVersionRequestHandler, EventDispatcherInterface $eventDispatcher, LoggerChannelFactoryInterface $loggerChannelFactory, TicketValidationInterface $ticketValidation, EntityTypeManagerInterface $entityTypeManager) {
    $this->newVersionRequestHandler = $newVersionRequestHandler;
    $this->eventDispatcher = $eventDispatcher;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->ticketValidation = $ticketValidation;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_epoetry.new_version_request_handler'),
      $container->get('event_dispatcher'),
      $container->get('logger.factory'),
      $container->get('oe_translation_epoetry.notification_ticket_validation'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Access checker for the admin view.
   *
   * Only accessible if the user has the permission and we have an ePoetry
   * translator configured.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   */
  public function adminViewAccess(AccountInterface $account) {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    if (!$account->hasPermission('translate any entity')) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    $cache->addCacheTags(['config:remote_translation_provider_list']);
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('remote_translation_provider')->loadByProperties([
      'plugin' => 'epoetry',
      'enabled' => TRUE,
    ]);
    if (!$translators) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    return AccessResult::allowed()->addCacheableDependency($cache);
  }

  /**
   * Notifications callback for ePoetry.
   *
   * Handles the SOAP requests from ePoetry that change statuses or deliver
   * translations.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current Symfony request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Symfony response containing the SOAP envelope.
   */
  public function notifications(Request $request): Response {
    $callback = NotificationEndpointResolver::resolve();
    $logger = $this->loggerChannelFactory->get('oe_translation_epoetry');
    $ticket_validation = NULL;
    if (NotificationTicketValidation::shouldUseTicketValidation()) {
      $ticket_validation = $this->ticketValidation;
    }
    $server_factory = new NotificationServerFactory($callback, $this->eventDispatcher, $logger, new Serializer(), $ticket_validation);
    $request_type = $request->getMethod();
    if ($request_type === 'GET') {
      $wsdl = $server_factory->getWsdl();
      $response = new Response($wsdl);
      $response->headers->set('Content-type', 'application/xml; charset=utf-8');
      return $response;
    }

    $psr_factory = new Psr17Factory();
    $psr_http_factory = new PsrHttpFactory($psr_factory, $psr_factory, $psr_factory, $psr_factory);
    $psr_request = $psr_http_factory->createRequest($request);
    $response = $server_factory->handle($psr_request);
    $http_foundation_factory = new HttpFoundationFactory();
    return $http_foundation_factory->createResponse($response);
  }

  /**
   * Route for marking a failed request as finished.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function finishFailedRequest(TranslationRequestEpoetryInterface $translation_request, Request $request): RedirectResponse {
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
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function finishFailedRequestAccess(TranslationRequestEpoetryInterface $translation_request, AccountInterface $account): AccessResultInterface {
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
   * Access callback for the createNewVersion request.
   *
   * The request can be made only if the user has the appropriate permission
   * and the request has the correct status.
   *
   * @param \Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface $translation_request
   *   The translation request.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function createNewVersionRequestAccess(TranslationRequestEpoetryInterface $translation_request, AccountInterface $account): AccessResultInterface {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    $cache->addCacheableDependency($translation_request);

    if (!$account->hasPermission('translate any entity')) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    $provider = $translation_request->getTranslatorProvider();
    $cache->addCacheableDependency($provider);
    if (!$provider->isEnabled()) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    $allowed = $this->newVersionRequestHandler->canCreateRequest($translation_request);
    if ($allowed) {
      return AccessResult::allowed()->addCacheableDependency($cache);
    }

    return AccessResult::forbidden()->addCacheableDependency($cache);
  }

}
