<?php

namespace Drupal\oe_translation_epoetry\Plugin\views\access;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\Routing\Route;

/**
 * Access plugin that controls access to the ePoetry requests view.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "oe_translation_epoetry_translation_requests",
 *   title = @Translation("ePoetry translation request"),
 *   help = @Translation("Access will be granted if the user can translate content and if ePoetry is enabled on the site.")
 * )
 */
class EpoetryTranslationRequests extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    // Defer to the access checker on the route.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', 'Drupal\oe_translation_epoetry\Controller\EpoetryController::adminViewAccess');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['config:remote_translation_provider_list'];
  }

}
