<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_test\Plugin\tmgmt\Translator;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation\ApplicableTranslatorInterface;
use Drupal\tmgmt_local\LocalTaskItemInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\oe_translation\LocalTranslatorInterface;
use Drupal\oe_translation\RouteProvidingTranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Drupal current user provider.
 *
 * @TranslatorPlugin(
 *   id = "oe_translation_test_translator",
 *   label = @Translation("Test translator"),
 *   description = @Translation("Test translator"),
 *   map_remote_languages = FALSE
 * )
 */
class TestTranslatorWithInterfaces extends TranslatorPluginBase implements ApplicableTranslatorInterface, RouteProvidingTranslatorInterface, LocalTranslatorInterface {

  /**
   * {@inheritdoc}
   */
  public function jobItemFormAlter(array &$form, FormStateInterface $form_state): void {
    // Do nothing for now.
  }

  /**
   * {@inheritdoc}
   */
  public function applies(EntityTypeInterface $entityType): bool {
    return $entityType->id() === 'node';
  }

  /**
   * {@inheritdoc}
   */
  public function contentTranslationOverviewAlter(array &$build, RouteMatchInterface $route_match, $entity_type_id): void {
    $build['test_translator'] = [
      '#markup' => 'Content overview altered',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function requestTranslation(JobInterface $job) {
    // Do nothing.
  }

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): RouteCollection {
    $collection = new RouteCollection();

    $route = new Route(
      '/test',
      [
        '_controller' => '\Drupal\oe_translation_test\Controller\TestController:testRoute',
      ],
      [
        '_access' => 'TRUE',
      ]
    );

    $collection->add('oe_translation_test.test_route', $route);
    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function localTaskItemFormAlter(array &$form, FormStateInterface $form_state): void {
    $form['test_alter'] = [
      '#markup' => 'Altered local task item form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function localTaskItemAccess(LocalTaskItemInterface $task_item, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation === 'test') {
      return AccessResult::forbidden('Access control works');
    }
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  public function localTaskItemBreadcrumbAlter(Breadcrumb &$breadcrumb, RouteMatchInterface $route_match, array $context): void {
    // Do nothing. We reply on the PermissionTranslator to test.
  }

}
