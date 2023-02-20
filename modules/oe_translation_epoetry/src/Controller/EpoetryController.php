<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Http\Discovery\Psr17Factory;
use Drupal\oe_translation_remote\TranslationRequestRemoteInterface;
use OpenEuropa\EPoetry\NotificationServerFactory;
use OpenEuropa\EPoetry\Serializer\Serializer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
   * Constructs a EpoetryController.
   */
  public function __construct(EpoetryOngoingNewVersionRequestHandlerInterface $newVersionRequestHandler) {
    $this->newVersionRequestHandler = $newVersionRequestHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_translation_epoetry.new_version_request_handler')
    );
  }

  /**
   * Notifications callback for ePoetry.
   *
   * Handles the SOAP requests from ePoetry that change statuses or deliver
   * translations.
   *
   * @todo add basic access check.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current Symfony request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Symfony response containing the SOAP envelope.
   */
  public function notifications(Request $request): Response {
    $callback = Url::fromRoute('oe_translation_epoetry.notifications_endpoint')->setAbsolute()->toString();
    $server_factory = new NotificationServerFactory($callback, \Drupal::service('event_dispatcher'), \Drupal::logger('epoetry'), new Serializer());
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

    $allowed = $this->newVersionRequestHandler->canCreateRequest($translation_request);
    if ($allowed) {
      return AccessResult::allowed()->addCacheableDependency($cache);
    }

    return AccessResult::forbidden()->addCacheableDependency($cache);
  }

}
