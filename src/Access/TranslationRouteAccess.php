<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Route access handler for the content translation routes.
 */
class TranslationRouteAccess implements AccessInterface {

  /**
   * Access callback to prevent the regular Drupal translations.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   * @param string $source
   *   The source language.
   * @param string $target
   *   The target language.
   * @param string $language
   *   The current language.
   * @param string $entity_type_id
   *   The entity type ID of the entity being translated.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account, string $source = NULL, string $target = NULL, string $language = NULL, string $entity_type_id = NULL): AccessResultInterface {
    $operation = $route->getRequirement('_access_oe_translation');
    // Nobody should ever create or edit translations using the regular core
    // flow.
    if (in_array($operation, ['create', 'update'])) {
      return AccessResult::forbidden();
    }

    // We allow the deletion to happen still in case.
    return AccessResult::neutral();
  }

}
