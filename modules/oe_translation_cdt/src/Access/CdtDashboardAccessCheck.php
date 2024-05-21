<?php

namespace Drupal\oe_translation_cdt\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access to the CDT dashboard.
 */
class CdtDashboardAccessCheck implements AccessCheckInterface {

  /**
   * Constructs a CdtDashboardAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.permissions']);
    if (!$account->hasPermission('translate any entity')) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    $cache->addCacheTags(['config:remote_translation_provider_list']);
    /** @var \Drupal\oe_translation_remote\Entity\RemoteTranslatorProviderInterface[] $translators */
    $translators = $this->entityTypeManager->getStorage('remote_translation_provider')->loadByProperties([
      'plugin' => 'cdt',
      'enabled' => TRUE,
    ]);
    if (!$translators) {
      return AccessResult::forbidden()->addCacheableDependency($cache);
    }

    return AccessResult::allowed()->addCacheableDependency($cache);
  }

}
