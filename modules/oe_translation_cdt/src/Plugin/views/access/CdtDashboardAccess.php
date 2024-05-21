<?php

namespace Drupal\oe_translation_cdt\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_translation_cdt\Access\AccessCheckInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that controls access to the CDT dashboard view.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "oe_translation_cdt_dashboard_access",
 *   title = @Translation("CDT dashboard access"),
 *   help = @Translation("Access will be granted if the user can translate content and if CDT is enabled on the site.")
 * )
 */
class CdtDashboardAccess extends AccessPluginBase implements CacheableDependencyInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new CdtDashboardAccess object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\oe_translation_cdt\Access\AccessCheckInterface $dashboardAccessCheck
   *   The access check service for the dashboard.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected AccessCheckInterface $dashboardAccessCheck) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_translation_cdt.dashboard_access_check'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): bool {
    return $this->dashboardAccessCheck->access($account)->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route): void {
    $route->setRequirement('_oe_translation_cdt_dashboard_access_check', 'TRUE');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return ['config:remote_translation_provider_list'];
  }

}
