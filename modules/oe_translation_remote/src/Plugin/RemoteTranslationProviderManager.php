<?php

declare(strict_types = 1);

namespace Drupal\oe_translation_remote\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * RemoteTranslationProvider plugin manager.
 */
class RemoteTranslationProviderManager extends DefaultPluginManager {

  /**
   * Constructs RemoteTranslationProviderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/RemoteTranslationProvider',
      $namespaces,
      $module_handler,
      'Drupal\oe_translation_remote\RemoteTranslationProviderInterface',
      'Drupal\oe_translation_remote\Annotation\RemoteTranslationProvider');

    $this->alterInfo('remote_translation_provider_info');
    $this->setCacheBackend($cache_backend, 'remote_translation_provider_plugins');
  }

}
