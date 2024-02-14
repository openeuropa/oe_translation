<?php

declare(strict_types=1);

namespace Drupal\oe_translation\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the translation request entity type.
 */
class TranslationRequestAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $access = parent::checkAccess($entity, $operation, $account);
    if (!$access->isNeutral()) {
      return $access;
    }

    $type = $entity->bundle();
    switch ($operation) {
      case 'view':
        $permission = $entity->isPublished() ? 'view translation request' : 'view unpublished translation request';
        return AccessResult::allowedIfHasPermission($account, $permission)->addCacheableDependency($entity)->cachePerPermissions();

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit ' . $type . ' translation request')->cachePerPermissions();

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete ' . $type . ' translation request')->cachePerPermissions();

      default:
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $permissions = [
      $this->entityType->getAdminPermission(),
      'create ' . $entity_bundle . ' translation request',
    ];
    return AccessResult::allowedIfHasPermissions($account, $permissions, 'OR');
  }

}
