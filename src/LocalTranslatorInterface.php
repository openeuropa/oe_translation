<?php

declare(strict_types = 1);

namespace Drupal\oe_translation;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tmgmt_local\LocalTaskItemInterface;

/**
 * Interface for TMGMT translator plugins that are meant to work locally.
 */
interface LocalTranslatorInterface {

  /**
   * Alters the edit form for the local task item.
   *
   * This is the form used for translating locally content.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function localTaskItemFormAlter(array &$form, FormStateInterface $form_state): void;

  /**
   * Intercepts the access control on the local task items entities.
   *
   * Plugins that don't require can return AccessResultNeutral.
   *
   * @param \Drupal\tmgmt_local\LocalTaskItemInterface $task_item
   *   The local task item.
   * @param string $operation
   *   The operation being performed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function localTaskItemAccess(LocalTaskItemInterface $task_item, string $operation, AccountInterface $account): AccessResultInterface;

  /**
   * Allows to alter the breadcrumb on the local task item canonical page.
   *
   * @param \Drupal\Core\Breadcrumb\Breadcrumb $breadcrumb
   *   The breadcrumb object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param array $context
   *   The breadcrumb context.
   */
  public function localTaskItemBreadcrumbAlter(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, array $context): void;

}
