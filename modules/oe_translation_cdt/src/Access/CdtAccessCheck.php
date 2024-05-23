<?php

declare(strict_types=1);

namespace Drupal\oe_translation_cdt\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access to the CDT translations.
 */
class CdtAccessCheck implements AccessInterface {

  /**
   * Constructs a CdtAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Checks the access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
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
