<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_poetry\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_poetry\Plugin\tmgmt\Translator\PoetryTranslator;
use Drupal\tmgmt\Entity\Controller\JobAccessControlHandler;
use Drupal\tmgmt\Entity\Job;

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
    /** @var \Drupal\tmgmt\Entity\JobInterface $entity */
    if ($operation !== 'delete') {
      return parent::checkAccess($entity, $operation, $account);
    }

    // Allow the owners of the jobs to delete jobs if they are unprocessed.
    if ($entity->isAuthor($account) && (int) $entity->getState() === Job::STATE_UNPROCESSED && $entity->getTranslatorPlugin() instanceof PoetryTranslator) {
      return AccessResult::allowed()->addCacheableDependency($entity)->cachePerUser();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
