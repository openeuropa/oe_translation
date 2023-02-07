<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_epoetry\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_epoetry\EpoetryOngoingNewVersionRequestHandlerInterface;
use Drupal\oe_translation_epoetry\TranslationRequestEpoetryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
