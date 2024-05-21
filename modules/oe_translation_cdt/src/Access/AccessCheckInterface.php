<?php

namespace Drupal\oe_translation_cdt\Access;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Interface for checking access.
 *
 * The interface is introduced since core AccessInterface is no longer used.
 */
interface AccessCheckInterface {

  /**
   * Checks the access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface;

}
