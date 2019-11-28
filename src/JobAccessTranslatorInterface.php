<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tmgmt\JobInterface;

/**
 * TMGMT translators that need to control access to Job entities.
 */
interface JobAccessTranslatorInterface {

  /**
   * Checks access to a given Job.
   *
   * @param \Drupal\tmgmt\JobInterface $job
   *   The job.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @see \Drupal\oe_translation\Access\JobAccessHandler
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access or NULL if we want to defer to the default access checking.
   */
  public function accessJob(JobInterface $job, string $operation, AccountInterface $account): ?AccessResultInterface;

}
