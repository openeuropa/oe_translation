<?php

declare(strict_types = 1);

namespace Drupal\oe_translation\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\JobAccessTranslatorInterface;
use Drupal\tmgmt\Entity\Controller\JobAccessControlHandler;

/**
 * Overriding the default access control handler of the Job entity.
 *
 * We need to do this because the original access handler forbids access to the
 * delete operation which means it cannot be altered with hook_entity_access().
 */
class JobAccessHandler extends JobAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\tmgmt\JobInterface $entity */
    $plugin = $entity->getTranslatorPlugin();
    if ($plugin instanceof JobAccessTranslatorInterface) {
      $access = $plugin->accessJob($entity, $operation, $account);
      if ($access instanceof AccessResultInterface) {
        return $access;
      }
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
